<?php

use App\Enums\BookingChargeCategory;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Guest;
use App\Models\StripeWebhookEvent;
use App\Models\User;

/**
 * Signs a fake Stripe event payload the same way Stripe itself does
 * (documented scheme: HMAC-SHA256 of "{timestamp}.{payload}"), so
 * StripeWebhookController's real signature verification is exercised
 * end to end — no network call to Stripe involved, only inbound webhook
 * handling is under test.
 */
function postStripeWebhook(array $payload, ?string $secret = null): \Illuminate\Testing\TestResponse
{
    $secret ??= config('services.stripe.webhook_secret');
    $timestamp = time();
    $json = json_encode($payload);
    $signature = hash_hmac('sha256', "{$timestamp}.{$json}", $secret);

    return test()->postJson('/webhooks/stripe', $payload, [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ]);
}

function paymentSucceededPayload(string $eventId, Booking $booking, string $kind, int $amountCents, string $paymentIntentId): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => $paymentIntentId,
            'object' => 'payment_intent',
            'amount' => $amountCents,
            'amount_received' => $amountCents,
            'currency' => 'usd',
            'status' => 'succeeded',
            'metadata' => ['booking_id' => (string) $booking->id, 'kind' => $kind],
        ]],
    ];
}

beforeEach(function () {
    $user = User::factory()->create();
    $this->guest = Guest::factory()->create(['user_id' => $user->id]);
    $this->booking = Booking::factory()->create([
        'guest_id' => $this->guest->id,
        'status' => BookingStatus::PendingPayment,
        'deposit_cents' => 30000,
    ]);
    $this->booking->charges()->create([
        'category' => BookingChargeCategory::Room,
        'description' => 'Room charge',
        'amount_cents' => 100000,
    ]);
});

it('records the payment and confirms the booking on payment_intent.succeeded', function () {
    $response = postStripeWebhook(paymentSucceededPayload('evt_1', $this->booking, 'deposit', 30000, 'pi_1'));

    $response->assertOk();
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    expect(BookingPayment::where('stripe_payment_intent_id', 'pi_1')->count())->toBe(1);
});

it('only ever processes a given stripe event once', function () {
    $payload = paymentSucceededPayload('evt_duplicate', $this->booking, 'deposit', 30000, 'pi_2');

    postStripeWebhook($payload)->assertOk();
    postStripeWebhook($payload)->assertOk();

    expect(BookingPayment::where('stripe_payment_intent_id', 'pi_2')->count())->toBe(1);
    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_duplicate')->count())->toBe(1);
});

it('rejects a payload with an invalid signature and creates no records', function () {
    $payload = paymentSucceededPayload('evt_bad_sig', $this->booking, 'deposit', 30000, 'pi_3');

    $response = postStripeWebhook($payload, secret: 'whsec_wrong_secret');

    $response->assertStatus(400);
    expect(BookingPayment::where('stripe_payment_intent_id', 'pi_3')->exists())->toBeFalse();
    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_bad_sig')->exists())->toBeFalse();
});

it('nets a full refund back to a zero balance on refund.updated', function () {
    postStripeWebhook(paymentSucceededPayload('evt_full_pay', $this->booking, 'deposit', 100000, 'pi_4'))->assertOk();
    // Pay the balance too so the booking is fully paid, matching a real
    // full-refund scenario (only paid amounts are ever refunded).
    $this->booking->update(['deposit_cents' => 100000]);

    $refundPayload = [
        'id' => 'evt_refund_1',
        'object' => 'event',
        'type' => 'refund.updated',
        'data' => ['object' => [
            'id' => 're_1',
            'object' => 'refund',
            'amount' => 100000,
            'currency' => 'usd',
            'payment_intent' => 'pi_4',
            'status' => 'succeeded',
            'metadata' => [],
        ]],
    ];

    postStripeWebhook($refundPayload)->assertOk();

    $booking = $this->booking->fresh(['charges', 'payments']);
    expect($booking->balanceDueCents())->toBe(0);
    expect(BookingPayment::where('stripe_refund_id', 're_1')->where('amount_cents', -100000)->exists())->toBeTrue();
});
