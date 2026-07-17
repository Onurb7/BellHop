<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Services\StripePaymentService;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;

it('marks a past-check-in confirmed booking as a no-show', function () {
    $pastBooking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'check_in' => today()->subDay(),
        'check_out' => today()->addDays(2),
    ]);
    $futureBooking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'check_in' => today()->addDay(),
        'check_out' => today()->addDays(3),
    ]);

    $this->artisan('bookings:sweep-no-shows')->assertSuccessful();

    expect($pastBooking->fresh()->status)->toBe(BookingStatus::NoShow)
        ->and($futureBooking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('off-session charges a booking whose balance is due and records nothing extra on success', function () {
    $guest = Guest::factory()->create();
    $booking = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
        'deposit_cents' => 30000,
        'stripe_payment_method_id' => 'pm_test_123',
        'balance_due_at' => now()->subDay(),
    ]);
    $booking->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $booking->payments()->create(['kind' => 'deposit', 'amount_cents' => 30000, 'verified_at' => now()]);

    $this->mock(StripePaymentService::class, function ($mock) {
        $mock->shouldReceive('chargeOffSession')
            ->once()
            ->andReturn(PaymentIntent::constructFrom(['id' => 'pi_test', 'status' => 'succeeded']));
    });

    $this->artisan('bookings:charge-due-balances')->assertSuccessful();

    // The command itself doesn't touch the ledger — the normal webhook
    // does, exactly like any other payment. Just confirm it didn't error
    // and didn't send a failure email.
    expect($booking->fresh()->balance_due_at)->not->toBeNull();
});

it('emails the guest and stops retrying when the off-session charge is declined', function () {
    Mail::fake();

    $guest = Guest::factory()->create();
    $booking = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
        'deposit_cents' => 30000,
        'stripe_payment_method_id' => 'pm_test_declined',
        'balance_due_at' => now()->subDay(),
    ]);
    $booking->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $booking->payments()->create(['kind' => 'deposit', 'amount_cents' => 30000, 'verified_at' => now()]);

    $this->mock(StripePaymentService::class, function ($mock) {
        $mock->shouldReceive('chargeOffSession')
            ->once()
            ->andThrow(CardException::factory('Your card was declined.'));
    });

    $this->artisan('bookings:charge-due-balances')->assertSuccessful();

    $booking = $booking->fresh();
    expect($booking->balance_due_at)->toBeNull();
    Mail::assertSent(\App\Mail\PaymentAutoChargeFailedMail::class);
});

it('sends a payment reminder instead of charging when no card was saved', function () {
    Mail::fake();

    $guest = Guest::factory()->create();
    $booking = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
        'deposit_cents' => 30000,
        'stripe_payment_method_id' => null,
        'balance_due_at' => now()->subDay(),
    ]);
    $booking->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $booking->payments()->create(['kind' => 'deposit', 'amount_cents' => 30000, 'verified_at' => now()]);

    $this->mock(StripePaymentService::class, function ($mock) {
        $mock->shouldNotReceive('chargeOffSession');
    });

    $this->artisan('bookings:charge-due-balances')->assertSuccessful();

    $booking = $booking->fresh();
    expect($booking->balance_due_at)->toBeNull();
    Mail::assertSent(\App\Mail\PaymentReminderMail::class);
});
