<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\ServicePricingType;
use App\Models\Booking;
use App\Models\Service;
use App\Services\ServicePurchaseService;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestReservationController extends Controller
{
    public function show(Booking $booking, Request $request): Response
    {
        $this->authorizeOwnership($booking, $request);

        $booking->load([
            'room.roomType',
            'guest',
            'charges' => fn ($query) => $query->orderBy('created_at'),
            'payments' => fn ($query) => $query->orderBy('created_at'),
        ]);

        return Inertia::render('Reservations/GuestShow', [
            'booking' => [
                'id' => $booking->id,
                'status' => $booking->status->value,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'nights' => $booking->check_in->diffInDays($booking->check_out),
                'total_cents' => $booking->totalCents(),
                'amount_paid_cents' => $booking->amountPaidCents(),
                'balance_due_cents' => $booking->balanceDueCents(),
                'invoice_generated_at' => $booking->invoice_generated_at?->toIso8601String(),
                'payable' => $this->isPayable($booking),
                'room' => [
                    'number' => $booking->room->number,
                    'floor' => $booking->room->floor,
                    'room_type' => $booking->room->roomType->name,
                ],
                'charges' => $booking->charges->map(fn ($charge) => [
                    'id' => $charge->id,
                    'category' => $charge->category->value,
                    'description' => $charge->description,
                    'amount_cents' => $charge->amount_cents,
                    'created_at' => $charge->created_at->toDateTimeString(),
                ]),
                'payments' => $booking->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'kind' => $payment->kind->value,
                    'amount_cents' => $payment->amount_cents,
                    'verified_at' => $payment->verified_at->toDateTimeString(),
                ]),
            ],
            'stripe_publishable_key' => config('services.stripe.key'),
            'services' => Service::where('active', true)->orderBy('name')->get()->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'unit_price_cents' => $service->unit_price_cents,
                'currency' => $service->currency,
                'pricing_type' => $service->pricing_type->value,
            ]),
        ]);
    }

    /**
     * Guest self-service equivalent of ReservationController::addService()
     * (staff front-desk purchase) — same restriction to an active stay,
     * same shared ServicePurchaseService.
     */
    public function purchaseService(Booking $booking, Request $request, ServicePurchaseService $servicePurchase): RedirectResponse
    {
        $this->authorizeOwnership($booking, $request);

        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'nights' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_unless(
            in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true),
            422,
            'Services can only be added to an active reservation.',
        );

        $service = Service::where('active', true)->findOrFail($data['service_id']);
        $totalNights = $booking->check_in->diffInDays($booking->check_out);

        [$quantity, $nights] = $service->pricing_type === ServicePricingType::PerNight
            ? [1, min($data['nights'] ?? $totalNights, $totalNights)]
            : [$data['quantity'] ?? 1, null];

        $servicePurchase->purchase($booking, $service, $quantity, $nights, auth()->id());

        return back()->with('success', "{$service->name} added.");
    }

    public function createPaymentIntent(Booking $booking, Request $request, StripePaymentService $stripe): JsonResponse
    {
        $this->authorizeOwnership($booking, $request);

        if (! $this->isPayable($booking)) {
            return response()->json(['message' => 'This reservation is no longer active and cannot be paid.'], 422);
        }

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

    private function authorizeOwnership(Booking $booking, Request $request): void
    {
        abort_unless($booking->guest_id === $request->user()->guest?->id, 403);
    }

    /**
     * A cancelled or no-show booking is a terminal, won't-happen state —
     * self-service payment doesn't make sense for either, independent of
     * whatever balance_due_cents happens to compute to (e.g. a booking
     * cancelled before ever being paid still has a positive balance).
     */
    private function isPayable(Booking $booking): bool
    {
        return ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::NoShow], true);
    }
}
