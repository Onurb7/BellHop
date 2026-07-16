<?php

namespace App\Http\Controllers;

use App\Enums\BookingChargeCategory;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingStatus;
use App\Jobs\GenerateBookingInvoice;
use App\Mail\ExistingAccountMail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid signature', 400);
        }

        // Inserted before any side effects run, checked before any are
        // attempted — a crash mid-processing gets retried by Stripe's
        // own webhook retry mechanism, but a fully-processed event is
        // never double-applied.
        $webhookEvent = StripeWebhookEvent::firstOrCreate(
            ['stripe_event_id' => $event->id],
            ['type' => $event->type, 'payload' => $event->toArray()],
        );

        if ($webhookEvent->processed_at !== null) {
            return response('Already processed', 200);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'refund.updated' => $this->handleRefundUpdated($event->data->object),
            default => null,
        };

        $webhookEvent->update(['processed_at' => now()]);

        return response('OK', 200);
    }

    private function handlePaymentSucceeded(\Stripe\PaymentIntent $intent): void
    {
        $bookingId = $intent->metadata['booking_id'] ?? null;
        $kind = $intent->metadata['kind'] ?? null;

        if (! $bookingId || ! $kind) {
            return;
        }

        $booking = Booking::find($bookingId);

        if (! $booking) {
            return;
        }

        $booking->payments()->create([
            'kind' => BookingPaymentKind::from($kind),
            'amount_cents' => $intent->amount_received,
            'stripe_payment_intent_id' => $intent->id,
            'verified_by' => null,
            'verified_at' => now(),
        ]);

        if ($booking->status === BookingStatus::PendingPayment) {
            $booking->confirm();
        }

        $this->provisionGuestAccount($booking);

        if ($booking->fresh()->balanceDueCents() <= 0) {
            GenerateBookingInvoice::dispatch($booking);
        }
    }

    /**
     * Only ever reached for a Stripe-paid booking whose guest has no
     * linked user yet — an already-authenticated guest paying via
     * GuestReservationController always has one, and a walk-in booking's
     * manual "Verify Payment" never touches Stripe at all — so this is,
     * by construction, only ever the public self-service flow.
     */
    private function provisionGuestAccount(Booking $booking): void
    {
        $booking->loadMissing('guest');
        $guest = $booking->guest;

        if ($guest === null || $guest->user_id !== null) {
            return;
        }

        $existingUser = User::where('email', $guest->email)->first();

        if ($existingUser) {
            // Never auto-linked — an unauthenticated checkout proving it
            // knows someone's email isn't proof it's really them.
            Mail::to($guest->email)->send(new ExistingAccountMail($booking));

            return;
        }

        $user = User::create([
            'first_name' => $guest->first_name,
            'last_name' => $guest->last_name,
            'email' => $guest->email,
            // Unusable until they follow the password-setup email below.
            'password' => Str::random(40),
        ]);

        $user->syncRoles(['guest']);
        $guest->update(['user_id' => $user->id]);

        Password::sendResetLink(['email' => $user->email]);
    }

    /**
     * `refund.updated` (not `charge.refunded`) — a Charge webhook payload
     * doesn't reliably include its nested `refunds` list, but a Refund
     * event's object *is* the Refund itself, with its own `metadata` and
     * `payment_intent` — confirmed by inspecting a real test-mode
     * payload. Fires on every status transition, so this only acts once
     * the refund has actually settled, and is guarded against a
     * theoretical double-fire on the same terminal status.
     */
    private function handleRefundUpdated(\Stripe\Refund $refund): void
    {
        if ($refund->status !== 'succeeded') {
            return;
        }

        $alreadyRecorded = BookingPayment::where('stripe_refund_id', $refund->id)->exists();

        if ($alreadyRecorded) {
            return;
        }

        $payment = BookingPayment::where('stripe_payment_intent_id', $refund->payment_intent)
            ->where('kind', '!=', BookingPaymentKind::Refund->value)
            ->first();

        if (! $payment) {
            return;
        }

        $refundedBy = $refund->metadata['refunded_by'] ?? null;
        $booking = $payment->booking;

        $booking->payments()->create([
            'kind' => BookingPaymentKind::Refund,
            'amount_cents' => -$refund->amount,
            'stripe_payment_intent_id' => $refund->payment_intent,
            'stripe_refund_id' => $refund->id,
            'verified_by' => $refundedBy,
            'verified_at' => now(),
        ]);

        // Mirrors the refund with an equal-and-opposite charge, the same
        // signed-delta pattern date/room changes already use — without
        // this, totalCents() keeps counting the refunded stay forever,
        // so balanceDueCents() would swing positive again after a full
        // refund instead of settling back to zero.
        $booking->charges()->create([
            'category' => BookingChargeCategory::Refund,
            'description' => 'Refund issued for '.ucfirst($payment->kind->value).' payment',
            'amount_cents' => -$refund->amount,
            'created_by' => $refundedBy,
        ]);

        // Only refreshes an invoice that already exists — a booking that
        // was never fully paid (and so never got one) doesn't need one
        // now just because it was cancelled and partially refunded.
        if ($booking->fresh()->hasInvoice()) {
            GenerateBookingInvoice::dispatch($booking);
        }
    }
}
