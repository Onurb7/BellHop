<?php

use App\Enums\BookingStatus;
use App\Mail\ExistingAccountMail;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // provisionGuestAccount() calls syncRoles(['guest']) on a newly
    // created account — the role row itself is seeded in production by
    // RoleAndDemoUserSeeder, not created by migrations.
    Role::findOrCreate('guest');
});

/**
 * Signs a fake Stripe event the same way postStripeWebhook() in
 * StripeWebhookTest.php does — duplicated locally rather than shared, so
 * this file reads standalone (see .claude/hotel-booking-plan.md's "don't
 * overdo it" testing brief).
 */
function payDeposit(Booking $booking, string $paymentIntentId): void
{
    $secret = config('services.stripe.webhook_secret');
    $timestamp = time();
    $payload = [
        'id' => "evt_{$paymentIntentId}",
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => $paymentIntentId,
            'object' => 'payment_intent',
            'amount' => $booking->deposit_cents,
            'amount_received' => $booking->deposit_cents,
            'currency' => 'usd',
            'status' => 'succeeded',
            'metadata' => ['booking_id' => (string) $booking->id, 'kind' => 'deposit'],
        ]],
    ];
    $json = json_encode($payload);
    $signature = hash_hmac('sha256', "{$timestamp}.{$json}", $secret);

    test()->postJson('/webhooks/stripe', $payload, ['Stripe-Signature' => "t={$timestamp},v1={$signature}"])
        ->assertOk();
}

function lockedDraft(): Booking
{
    return Booking::factory()->create([
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(15),
        'deposit_cents' => 30000,
    ]);
}

it('never reuses an existing linked guest row when the typed email already has an account', function () {
    $existingUser = User::factory()->create(['email' => 'returning.guest@example.test']);
    $existingGuest = Guest::factory()->create(['user_id' => $existingUser->id, 'email' => 'returning.guest@example.test']);

    $booking = lockedDraft();

    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Someone',
        'last_name' => 'Else',
        'email' => 'returning.guest@example.test',
        'phone' => null,
        'address' => null,
    ])->assertRedirect(route('booking.show', $booking));

    $booking->refresh();

    expect($booking->guest_id)->not->toBe($existingGuest->id)
        ->and($booking->guest->user_id)->toBeNull();
});

it('keeps an existing-email guest unlinked and never auto-logs them in after payment', function () {
    Mail::fake();

    $existingUser = User::factory()->create(['email' => 'returning.guest@example.test']);

    $booking = lockedDraft();
    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Someone',
        'last_name' => 'Else',
        'email' => 'returning.guest@example.test',
        'phone' => null,
        'address' => null,
    ]);
    $booking->refresh();

    payDeposit($booking, 'pi_existing_email');

    expect($booking->guest->fresh()->user_id)->toBeNull();

    $confirmationUrl = URL::temporarySignedRoute('booking.confirmation', now()->addHours(2), ['booking' => $booking]);
    $this->get($confirmationUrl);

    $this->assertGuest();
    Mail::assertSent(ExistingAccountMail::class);
});

it('provisions a brand-new account and auto-logs the guest in after payment for a new email', function () {
    Mail::fake();

    $booking = lockedDraft();
    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Brand',
        'last_name' => 'New',
        'email' => 'brand.new@example.test',
        'phone' => null,
        'address' => null,
    ]);
    $booking->refresh();

    payDeposit($booking, 'pi_new_email');

    $newUser = User::where('email', 'brand.new@example.test')->first();
    expect($newUser)->not->toBeNull();
    expect($booking->guest->fresh()->user_id)->toBe($newUser->id);

    $confirmationUrl = URL::temporarySignedRoute('booking.confirmation', now()->addHours(2), ['booking' => $booking]);
    $this->get($confirmationUrl);

    $this->assertAuthenticatedAs($newUser);
    expect(DB::table('password_reset_tokens')->where('email', 'brand.new@example.test')->exists())->toBeTrue();
});
