<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingChargeCategory;
use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use App\Services\RoomAvailabilityService;
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
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        try {
            $booking = $availability->lock(
                (int) $data['room_id'],
                Carbon::parse($data['check_in']),
                Carbon::parse($data['check_out']),
            );
        } catch (RoomUnavailableException) {
            return back()->withErrors(['room_id' => 'That room was just taken — please pick another, or adjust your dates.']);
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

            return Inertia::render('Public/Booking/GuestDetails', [
                'booking' => [
                    'id' => $booking->id,
                    'check_in' => $booking->check_in->toDateString(),
                    'check_out' => $booking->check_out->toDateString(),
                    'expires_at' => $booking->expires_at->toIso8601String(),
                    'nights' => $nights,
                    'total_cents' => $roomChargeCents,
                    'deposit_cents' => (int) round($roomChargeCents * 0.3),
                    'room' => [
                        'number' => $booking->room->number,
                        'room_type' => $booking->room->roomType->name,
                    ],
                ],
            ]);
        }

        $confirmationUrl = URL::temporarySignedRoute(
            'booking.confirmation',
            now()->addHours(2),
            ['booking' => $booking],
        );

        return Inertia::render('Public/Booking/Pay', [
            'booking' => [
                'id' => $booking->id,
                'total_cents' => $booking->totalCents(),
                'deposit_cents' => $booking->deposit_cents,
                'balance_due_cents' => $booking->balanceDueCents(),
                'room' => [
                    'number' => $booking->room->number,
                    'room_type' => $booking->room->roomType->name,
                ],
            ],
            'stripe_publishable_key' => config('services.stripe.key'),
            'confirmation_url' => $confirmationUrl,
        ]);
    }

    public function storeGuest(Booking $booking, Request $request, RoomAvailabilityService $availability): RedirectResponse
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
        ]);

        $guest = $this->resolveGuest($data);

        $booking->loadMissing('room.roomType');
        $nights = $booking->check_in->diffInDays($booking->check_out);
        $roomChargeCents = $nights * $booking->room->roomType->base_rate_cents;

        DB::transaction(function () use ($booking, $guest, $roomChargeCents, $nights) {
            $booking->update([
                'guest_id' => $guest->id,
                'expires_at' => null,
                'deposit_cents' => (int) round($roomChargeCents * 0.3),
            ]);

            $booking->charges()->create([
                'category' => BookingChargeCategory::Room,
                'description' => "Room charge: {$nights} night(s) at {$booking->room->number}",
                'amount_cents' => $roomChargeCents,
            ]);
        });

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
        }

        return redirect()->route('rooms.index');
    }

    public function createPaymentIntent(Booking $booking, StripePaymentService $stripe): JsonResponse
    {
        abort_if($booking->guest_id === null, 422, 'This reservation has no guest details yet.');
        abort_if($booking->status !== BookingStatus::PendingPayment, 422, 'This reservation is no longer awaiting payment.');

        $booking->loadMissing('payments', 'charges');

        ['kind' => $kind, 'amount_cents' => $amountCents] = $booking->nextPaymentKind();

        if ($amountCents <= 0) {
            return response()->json(['message' => 'Nothing is currently due on this reservation.'], 422);
        }

        $intent = $stripe->createPaymentIntent($booking, $kind, $amountCents);

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
