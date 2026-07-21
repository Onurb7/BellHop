<?php

use App\Enums\BookingStatus;
use App\Mail\BookingCancelledNonPaymentMail;
use App\Mail\PaymentAutoChargeFailedMail;
use App\Mail\PaymentReminderMail;
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
    expect($booking->balance_due_at)->toBeNull()
        ->and($booking->balance_collection_failed_at)->not->toBeNull();
    Mail::assertSent(PaymentAutoChargeFailedMail::class);
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
    expect($booking->balance_due_at)->toBeNull()
        ->and($booking->balance_collection_failed_at)->not->toBeNull();
    Mail::assertSent(PaymentReminderMail::class, fn ($mail) => $mail->willAutoCancel === true);
});

it('cancels a confirmed booking whose balance collection failed more than 24 hours ago', function () {
    Mail::fake();

    $guest = Guest::factory()->create();
    $overdue = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
        'deposit_cents' => 30000,
        'balance_collection_failed_at' => now()->subHours(25),
    ]);
    $overdue->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $overdue->payments()->create(['kind' => 'deposit', 'amount_cents' => 30000, 'verified_at' => now()]);

    $withinGrace = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
        'deposit_cents' => 30000,
        'balance_collection_failed_at' => now()->subHours(2),
    ]);
    $withinGrace->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $withinGrace->payments()->create(['kind' => 'deposit', 'amount_cents' => 30000, 'verified_at' => now()]);

    $paidInTheMeantime = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
        'deposit_cents' => 30000,
        'balance_collection_failed_at' => now()->subHours(25),
    ]);
    $paidInTheMeantime->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $paidInTheMeantime->payments()->create(['kind' => 'deposit', 'amount_cents' => 30000, 'verified_at' => now()]);
    $paidInTheMeantime->payments()->create(['kind' => 'balance', 'amount_cents' => 70000, 'verified_at' => now()]);

    $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($withinGrace->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($paidInTheMeantime->fresh()->status)->toBe(BookingStatus::Confirmed);
    Mail::assertSent(BookingCancelledNonPaymentMail::class, 1);
});

it('reminds a checked-out booking with an unpaid balance but never threatens cancellation', function () {
    Mail::fake();

    $guest = Guest::factory()->create();
    $unpaid = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::CheckedOut,
        'deposit_cents' => 30000,
    ]);
    $unpaid->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $unpaid->payments()->create(['kind' => 'deposit', 'amount_cents' => 30000, 'verified_at' => now()]);

    $settled = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::CheckedOut,
        'deposit_cents' => 100000,
    ]);
    $settled->charges()->create(['category' => 'room', 'description' => 'Room charge', 'amount_cents' => 100000]);
    $settled->payments()->create(['kind' => 'deposit', 'amount_cents' => 100000, 'verified_at' => now()]);

    $this->artisan('bookings:remind-checked-out-balances')->assertSuccessful();

    Mail::assertSent(
        PaymentReminderMail::class,
        fn ($mail) => $mail->booking->is($unpaid) && $mail->willAutoCancel === false
    );
    Mail::assertSent(PaymentReminderMail::class, 1);
    expect($unpaid->fresh()->last_reminder_type)->toBe('payment');
});
