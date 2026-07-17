<?php

namespace App\Services;

use App\Enums\BookingPaymentKind;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Guest;
use Stripe\Customer;
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

    public function createPaymentIntent(Booking $booking, BookingPaymentKind $kind, int $amountCents, bool $saveCard = false): PaymentIntent
    {
        // A deposit that doesn't cover the full total is the deposit-plan
        // case. `setup_future_usage` is only requested when the guest
        // explicitly opted in (Public\BookingController::createPaymentIntent()
        // — never assumed) — bookings:charge-due-balances can only
        // off-session charge the rest 3 days before check-in for bookings
        // that consented; everyone else gets a reminder email instead.
        $isDepositPlan = $kind === BookingPaymentKind::Deposit && $amountCents < $booking->totalCents();

        return $this->client->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'usd',
            // Explicit, not `automatic_payment_methods` — the embedded
            // Card Element only ever collects card details, never a
            // redirect-based method (e.g. Link), so confirming
            // client-side via `confirmCardPayment` (no `return_url`)
            // only works if the intent is restricted to card up front.
            'payment_method_types' => ['card'],
            ...($isDepositPlan && $saveCard ? ['setup_future_usage' => 'off_session'] : []),
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'kind' => $kind->value,
            ],
        ]);
    }

    /**
     * Stripe requires a PaymentMethod be attached to a Customer before it
     * can be reused on a later, separate PaymentIntent (confirmed live —
     * a bare `payment_method` + `off_session: true` charge without a
     * Customer is rejected with "cannot be attached"). Called once, right
     * after the deposit succeeds (see
     * StripeWebhookController::saveCardForBalanceAutoCharge()), never at
     * intent-creation time — only a genuinely-succeeded deposit needs its
     * card kept around for the balance auto-charge 3 days later.
     */
    public function attachPaymentMethodToNewCustomer(Guest $guest, string $paymentMethodId): Customer
    {
        $customer = $this->client->customers->create([
            'email' => $guest->email,
            'name' => $guest->name,
        ]);

        $this->client->paymentMethods->attach($paymentMethodId, ['customer' => $customer->id]);

        return $customer;
    }

    /**
     * Charges the balance off-session using the card saved from the
     * deposit — called only by the bookings:charge-due-balances command.
     * Throws \Stripe\Exception\CardException on decline/
     * authentication_required, left to the caller to handle.
     */
    public function chargeOffSession(Booking $booking, int $amountCents): PaymentIntent
    {
        return $this->client->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'usd',
            'customer' => $booking->stripe_customer_id,
            'payment_method' => $booking->stripe_payment_method_id,
            'confirm' => true,
            'off_session' => true,
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'kind' => BookingPaymentKind::Balance->value,
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
