<?php

namespace App\Services;

use App\Enums\BookingPaymentKind;
use App\Models\Booking;
use App\Models\BookingPayment;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

class StripePaymentService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function createPaymentIntent(Booking $booking, BookingPaymentKind $kind, int $amountCents): PaymentIntent
    {
        return $this->client->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'usd',
            // Explicit, not `automatic_payment_methods` — the embedded
            // Card Element only ever collects card details, never a
            // redirect-based method (e.g. Link), so confirming
            // client-side via `confirmCardPayment` (no `return_url`)
            // only works if the intent is restricted to card up front.
            'payment_method_types' => ['card'],
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'kind' => $kind->value,
            ],
        ]);
    }

    /**
     * Refunds a payment in full by its PaymentIntent — this app only
     * ever issues one full refund per PaymentIntent (see
     * StripeWebhookController), never a partial one.
     */
    public function refund(BookingPayment $payment): Refund
    {
        return $this->client->refunds->create([
            'payment_intent' => $payment->stripe_payment_intent_id,
            'metadata' => [
                'refunded_by' => (string) auth()->id(),
            ],
        ]);
    }
}
