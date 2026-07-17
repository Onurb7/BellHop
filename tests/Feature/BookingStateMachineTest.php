<?php

use App\Enums\BookingStatus;
use App\Models\Booking;

it('checks in a confirmed booking but refuses any other status', function () {
    $confirmed = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    $confirmed->checkIn();
    expect($confirmed->status)->toBe(BookingStatus::CheckedIn);

    $pending = Booking::factory()->create(['status' => BookingStatus::PendingPayment]);
    expect(fn () => $pending->checkIn())->toThrow(RuntimeException::class);
});

it('checks out a checked-in booking but refuses any other status', function () {
    $checkedIn = Booking::factory()->create(['status' => BookingStatus::CheckedIn]);
    $checkedIn->checkOut();
    expect($checkedIn->status)->toBe(BookingStatus::CheckedOut);

    $confirmed = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    expect(fn () => $confirmed->checkOut())->toThrow(RuntimeException::class);
});

it('marks a confirmed booking a no-show but refuses any other status', function () {
    $confirmed = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    $confirmed->markNoShow();
    expect($confirmed->status)->toBe(BookingStatus::NoShow);

    $checkedIn = Booking::factory()->create(['status' => BookingStatus::CheckedIn]);
    expect(fn () => $checkedIn->markNoShow())->toThrow(RuntimeException::class);
});
