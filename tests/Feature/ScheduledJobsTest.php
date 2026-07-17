<?php

use App\Enums\BookingStatus;
use App\Models\Booking;

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
