<?php

use App\Enums\BookingChargeCategory;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingStatus;
use App\Models\Booking;

it('sums charges and payments live rather than trusting a cached total', function () {
    $booking = Booking::factory()->create(['deposit_cents' => 30000]);
    $booking->charges()->create(['category' => BookingChargeCategory::Room, 'description' => 'Room charge', 'amount_cents' => 100000]);
    $booking->payments()->create(['kind' => BookingPaymentKind::Deposit, 'amount_cents' => 30000, 'verified_at' => now()]);

    expect($booking->totalCents())->toBe(100000)
        ->and($booking->amountPaidCents())->toBe(30000)
        ->and($booking->balanceDueCents())->toBe(70000);
});

it('nets a full refund back to a zero balance via the offsetting charge', function () {
    $booking = Booking::factory()->create(['deposit_cents' => 100000]);
    $booking->charges()->create(['category' => BookingChargeCategory::Room, 'description' => 'Room charge', 'amount_cents' => 100000]);
    $booking->payments()->create(['kind' => BookingPaymentKind::Deposit, 'amount_cents' => 100000, 'verified_at' => now()]);

    // Mirrors what StripeWebhookController::handleRefundUpdated() does:
    // a negative payment plus an equal-and-opposite negative charge.
    $booking->payments()->create(['kind' => BookingPaymentKind::Refund, 'amount_cents' => -100000, 'verified_at' => now()]);
    $booking->charges()->create(['category' => BookingChargeCategory::Refund, 'description' => 'Refund issued', 'amount_cents' => -100000]);

    expect($booking->balanceDueCents())->toBe(0);
});

it('progresses next payment kind from deposit to balance to additional', function () {
    $booking = Booking::factory()->create(['deposit_cents' => 30000]);
    $booking->charges()->create(['category' => BookingChargeCategory::Room, 'description' => 'Room charge', 'amount_cents' => 100000]);

    expect($booking->nextPaymentKind())->toBe(['kind' => BookingPaymentKind::Deposit, 'amount_cents' => 30000]);

    $booking->payments()->create(['kind' => BookingPaymentKind::Deposit, 'amount_cents' => 30000, 'verified_at' => now()]);
    $booking->refresh();

    expect($booking->nextPaymentKind())->toBe(['kind' => BookingPaymentKind::Balance, 'amount_cents' => 70000]);

    $booking->payments()->create(['kind' => BookingPaymentKind::Balance, 'amount_cents' => 70000, 'verified_at' => now()]);
    $booking->refresh();

    expect($booking->nextPaymentKind())->toBe(['kind' => BookingPaymentKind::Additional, 'amount_cents' => 0]);
});

it('confirms a pending_payment booking but refuses any other status', function () {
    $pending = Booking::factory()->create(['status' => BookingStatus::PendingPayment]);
    $pending->confirm();
    expect($pending->status)->toBe(BookingStatus::Confirmed);

    $alreadyConfirmed = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    expect(fn () => $alreadyConfirmed->confirm())->toThrow(RuntimeException::class);
});

it('cancels from pending_payment or confirmed but refuses any other status', function () {
    $confirmed = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    $confirmed->cancel();
    expect($confirmed->status)->toBe(BookingStatus::Cancelled);

    $checkedOut = Booking::factory()->create(['status' => BookingStatus::CheckedOut]);
    expect(fn () => $checkedOut->cancel())->toThrow(RuntimeException::class);
});
