<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingChargeCategory;
use App\Enums\BookingStatus;
use App\Enums\ServicePricingType;
use App\Exceptions\RoomUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Service;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\RoomAvailabilityService;
use App\Services\ServicePurchaseService;
use App\Services\StripePaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function lock(Request $request, RoomAvailabilityService $availability): RedirectResponse
    {
        $availability->sweepExpiredDrafts();

        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        try {
            $booking = $availability->lock(
                (int) $data['room_id'],
                Carbon::parse($data['check_in']),
                Carbon::parse($data['check_out']),
            );
        } catch (RoomUnavailableException) {
            // Deliberately neutral wording: this fires both for a genuine
            // race (available in a search list moments ago, taken since)
            // and for dates typed directly on the room page that were
            // never available — "just taken" implied the latter case too,
            // which reads as misleading when nobody actually beat you to it.
            return back()->withErrors(['room_id' => 'This room isn\'t available for the selected dates — please try different dates.']);
        }

        return redirect()->route('booking.show', $booking);
    }

    public function show(Booking $booking, RoomAvailabilityService $availability): RedirectResponse|Response
    {
        if ($booking->guest_id === null && ! $availability->isLiveDraft($booking)) {
            return redirect()->route('rooms.index')
                ->with('error', 'That hold has expired or was already completed — please search again.');
        }

        $booking->loadMissing('room.roomType', 'guest');

        if ($booking->guest_id === null) {
            $nights = $booking->check_in->diffInDays($booking->check_out);
            $roomChargeCents = $nights * $booking->room->roomType->base_rate_cents;
            // Mirrors storeGuest()'s canOfferDeposit exactly — this is
            // only a preview, but it must match what actually gets
            // charged once guest details are submitted, or the guest
            // sees one number here and a different one on the payment
            // page.
            $canOfferDeposit = now()->addDays(3)->lt($booking->check_in);

            return Inertia::render('Public/Booking/GuestDetails', [
                'booking' => [
                    'id' => $booking->id,
                    'check_in' => $booking->check_in->toDateString(),
                    'check_out' => $booking->check_out->toDateString(),
                    'expires_at' => $booking->expires_at->toIso8601String(),
                    'nights' => $nights,
                    'total_cents' => $roomChargeCents,
                    'deposit_cents' => $canOfferDeposit ? (int) round($roomChargeCents * 0.3) : $roomChargeCents,
                    'is_deposit_plan' => $canOfferDeposit,
                    // Not yet converted to USD — that only happens once a
                    // real charge row is created in storeGuest(). This is
                    // still just the room type's own listed price.
                    'currency' => $booking->room->roomType->currency,
                    'room' => [
                        'number' => $booking->room->number,
                        'room_type' => $booking->room->roomType->name,
                    ],
                ],
                'services' => Service::where('active', true)->orderBy('name')->get()->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'unit_price_cents' => $service->unit_price_cents,
                    'currency' => $service->currency,
                    'pricing_type' => $service->pricing_type->value,
                    'thumb_url' => $service->getFirstMediaUrl('images', 'thumb') ?: null,
                ]),
            ]);
        }

        $confirmationUrl = URL::temporarySignedRoute(
            'booking.confirmation',
            now()->addHours(2),
            ['booking' => $booking],
        );

        $isDepositPlan = $booking->isDepositPlan();

        return Inertia::render('Public/Booking/Pay', [
            'booking' => [
                'id' => $booking->id,
                'status' => $booking->status->value,
                'expires_at' => $booking->expires_at?->toIso8601String(),
                'total_cents' => $booking->totalCents(),
                'deposit_cents' => $booking->deposit_cents,
                'balance_due_cents' => $booking->balanceDueCents(),
                'is_deposit_plan' => $isDepositPlan,
                'balance_auto_charge_date' => $isDepositPlan ? $booking->check_in->copy()->subDays(3)->toFormattedDateString() : null,
                'room' => [
                    'number' => $booking->room->number,
                    'room_type' => $booking->room->roomType->name,
                ],
            ],
            'stripe_publishable_key' => config('services.stripe.key'),
            'confirmation_url' => $confirmationUrl,
        ]);
    }

    public function storeGuest(Booking $booking, Request $request, RoomAvailabilityService $availability, ExchangeRateService $exchangeRates, ServicePurchaseService $servicePurchase): RedirectResponse
    {
        if (! $availability->isLiveDraft($booking)) {
            return redirect()->route('rooms.index')
                ->with('error', 'That hold has expired or was already completed — please search again.');
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'services' => ['array'],
            'services.*' => ['integer', 'exists:services,id'],
        ]);

        $guest = $this->resolveGuest($data);

        $booking->loadMissing('room.roomType');
        $nights = $booking->check_in->diffInDays($booking->check_out);
        // Real money always stays USD — the room type's own price gets
        // converted once, right here, before it ever becomes a permanent
        // ledger entry. Everything downstream (deposit, Stripe amounts,
        // balance math) just works in USD cents from this point on.
        $rateUsdCents = $exchangeRates->convertCents(
            $booking->room->roomType->base_rate_cents,
            $booking->room->roomType->currency,
            'USD',
        );
        $roomChargeCents = $nights * $rateUsdCents;

        // A 30% deposit only makes sense if there's still time for the
        // balance to be auto-charged 3 days before check-in — otherwise
        // full payment is required up front, same as the plan's original
        // (previously unenforced) rule.
        $canOfferDeposit = now()->addDays(3)->lt($booking->check_in);
        $depositCents = $canOfferDeposit ? (int) round($roomChargeCents * 0.3) : $roomChargeCents;

        DB::transaction(function () use ($booking, $guest, $roomChargeCents, $nights, $depositCents) {
            $booking->update([
                'guest_id' => $guest->id,
                // A fresh hold window, not null — an abandoned checkout
                // past this point is picked up by the scheduled
                // bookings:cancel-expired-holds command instead of
                // blocking the room forever.
                'expires_at' => now()->addMinutes(15),
                'deposit_cents' => $depositCents,
            ]);

            $booking->charges()->create([
                'category' => BookingChargeCategory::Room,
                'description' => "Room charge: {$nights} night(s) at {$booking->room->number}",
                'amount_cents' => $roomChargeCents,
            ]);
        });

        // Deliberately outside the transaction above and after it commits —
        // each selected service is applied via the same shared purchase
        // logic post-booking purchases use, one row at a time.
        $selectedServices = Service::where('active', true)->whereIn('id', $data['services'] ?? [])->get();

        foreach ($selectedServices as $service) {
            $servicePurchase->purchase(
                $booking,
                $service,
                1,
                $service->pricing_type === ServicePricingType::PerNight ? $nights : null,
                auth()->id(),
            );
        }

        return redirect()->route('booking.show', $booking);
    }

    /**
     * Deliberately does NOT reuse an existing `guests` row that's already
     * linked to a user account, unlike the staff walk-in flow's plain
     * `Guest::firstOrCreate()` — an unauthenticated checkout must never
     * be able to attach a new booking to somebody else's account just by
     * typing their email. A fresh, unlinked Guest row is created instead;
     * StripeWebhookController::provisionGuestAccount() (triggered once
     * the deposit is paid) is what emails them that an account already
     * exists, without touching it.
     */
    private function resolveGuest(array $data): Guest
    {
        $hasAccount = User::where('email', $data['email'])->exists();

        if (! $hasAccount) {
            return Guest::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                ],
            );
        }

        return Guest::create([
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
    }

    public function abandon(Booking $booking): RedirectResponse
    {
        if ($booking->guest_id === null) {
            $booking->delete();
        } elseif ($booking->status === BookingStatus::PendingPayment) {
            // Once guest details are attached the booking can no longer
            // just be deleted (real charge/payment rows may already
            // reference it) — cancel() is what actually releases the
            // room via the exclusion constraint. Guarded to PendingPayment
            // only: a booking that has since been confirmed (e.g. the
            // guest paid in another tab) must never be cancelled here.
            $booking->cancel();
        }

        return redirect()->route('rooms.index');
    }

    public function createPaymentIntent(Request $request, Booking $booking, StripePaymentService $stripe): JsonResponse
    {
        abort_if($booking->guest_id === null, 422, 'This reservation has no guest details yet.');
        abort_if($booking->status !== BookingStatus::PendingPayment, 422, 'This reservation is no longer awaiting payment.');

        $booking->loadMissing('payments', 'charges');

        ['kind' => $kind, 'amount_cents' => $amountCents] = $booking->nextPaymentKind();

        if ($amountCents <= 0) {
            return response()->json(['message' => 'Nothing is currently due on this reservation.'], 422);
        }

        // Explicit guest opt-in, never assumed — see Public/Booking/Pay.vue's
        // unchecked-by-default checkbox. Irrelevant for a full-payment
        // booking (createPaymentIntent() only ever saves a card for a
        // genuine deposit-plan booking regardless of this flag).
        $intent = $stripe->createPaymentIntent($booking, $kind, $amountCents, $request->boolean('save_card'));

        return response()->json([
            'client_secret' => $intent->client_secret,
            'amount_cents' => $amountCents,
            'kind' => $kind->value,
        ]);
    }

    /**
     * Signed-URL only (see routes/public.php) — a bare booking ID isn't
     * enough to reach this, since this is the one step that can start a
     * session for a brand-new account. Every other step in the wizard
     * uses the plain booking ID like the rest of the app already does.
     */
    public function confirmation(Booking $booking): RedirectResponse|Response
    {
        $booking->loadMissing('guest.user');

        if ($booking->status !== BookingStatus::Confirmed) {
            return Inertia::render('Public/Booking/Confirming');
        }

        if (! Auth::check() && $booking->guest->user_id !== null) {
            Auth::login($booking->guest->user);
            request()->session()->regenerate();
        }

        return redirect()->route('guest-reservations.show', $booking);
    }
}
