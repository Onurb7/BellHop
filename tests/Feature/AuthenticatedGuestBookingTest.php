<?php

use App\Enums\BookingStatus;
use App\Mail\ExistingAccountMail;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

/**
 * Same `data-page` scraping helper as BookingDepositPlanTest.php — each
 * Pest file can safely redeclare it, confirmed elsewhere in this suite.
 */
function inertiaPropsForAuthGuest(TestResponse $response): array
{
    preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $matches);

    return json_decode($matches[1], true)['props'];
}

/**
 * Same shape as GuestAutoLinkingTest.php's lockedDraft() — a fresh,
 * unattached hold, as RoomAvailabilityService::lock() would produce.
 */
function lockedDraftForAuthGuest(): Booking
{
    return Booking::factory()->create([
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(15),
        'deposit_cents' => 30000,
    ]);
}

/**
 * Signs a fake Stripe event, mirroring payDeposit() in
 * GuestAutoLinkingTest.php / StripeWebhookTest.php.
 */
function payDepositForAuthGuest(Booking $booking, string $paymentIntentId): void
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

beforeEach(function () {
    Role::findOrCreate('guest');
});

it('shows a guest_account summary for an authenticated guest on the details step', function () {
    $user = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test']);
    $user->assignRole('guest');
    Guest::factory()->create(['user_id' => $user->id, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test']);

    $booking = lockedDraftForAuthGuest();

    $props = inertiaPropsForAuthGuest($this->actingAs($user)->get("/book/{$booking->id}"));

    expect($props['guest_account'])->toBe([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
    ]);
});

it('has no guest_account prop for an anonymous visitor', function () {
    $booking = lockedDraftForAuthGuest();

    $props = inertiaPropsForAuthGuest($this->get("/book/{$booking->id}"));

    expect($props['guest_account'])->toBeNull();
});

it('attaches a booking to the authenticated guest\'s existing linked row, not a new one, without requiring identity fields', function () {
    $user = User::factory()->create();
    $user->assignRole('guest');
    $guest = Guest::factory()->create(['user_id' => $user->id]);

    $booking = lockedDraftForAuthGuest();

    $this->actingAs($user)
        ->post("/book/{$booking->id}/guest", [])
        ->assertRedirect(route('booking.show', $booking));

    $booking->refresh();

    expect($booking->guest_id)->toBe($guest->id);
    expect(Guest::count())->toBe(1);
});

it('creates and links a guest row for an authenticated user who has never booked before', function () {
    $user = User::factory()->create(['first_name' => 'Brand', 'last_name' => 'New', 'email' => 'brand.new.guest@example.test']);
    $user->assignRole('guest');

    expect($user->guest)->toBeNull();

    $booking = lockedDraftForAuthGuest();

    $this->actingAs($user)
        ->post("/book/{$booking->id}/guest", [])
        ->assertRedirect(route('booking.show', $booking));

    $booking->refresh();
    $newGuest = Guest::where('user_id', $user->id)->first();

    expect($newGuest)->not->toBeNull()
        ->and($newGuest->email)->toBe('brand.new.guest@example.test')
        ->and($booking->guest_id)->toBe($newGuest->id);
});

it('shows a booking made while authenticated on that guest\'s own dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('guest');
    $guest = Guest::factory()->create(['user_id' => $user->id]);

    $booking = lockedDraftForAuthGuest();
    $this->actingAs($user)->post("/book/{$booking->id}/guest", []);

    $props = inertiaPropsForAuthGuest($this->actingAs($user)->get('/dashboard'));

    expect(collect($props['reservations']['active'])->pluck('id'))->toContain($booking->id);
});

it('never emails an existing-account notice or disturbs the session when an already-logged-in guest completes payment', function () {
    Mail::fake();

    $user = User::factory()->create();
    $user->assignRole('guest');
    Guest::factory()->create(['user_id' => $user->id]);

    $booking = lockedDraftForAuthGuest();
    $this->actingAs($user)->post("/book/{$booking->id}/guest", []);
    $booking->refresh();

    payDepositForAuthGuest($booking, 'pi_authenticated_guest');

    Mail::assertNotSent(ExistingAccountMail::class);

    $confirmationUrl = URL::temporarySignedRoute('booking.confirmation', now()->addHours(2), ['booking' => $booking]);
    $this->actingAs($user)->get($confirmationUrl);

    $this->assertAuthenticatedAs($user);
});
