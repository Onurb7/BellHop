<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Spatie\Permission\Models\Role;

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

it('refuses a date/room change once the balance has already been charged', function () {
    Role::findOrCreate('staff');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    $booking->payments()->create(['kind' => 'balance', 'amount_cents' => 70000, 'verified_at' => now()]);
    $newRoom = Room::factory()->create();

    $this->actingAs($staff)
        ->post("/reservations/{$booking->id}/date-change/apply", [
            'room_id' => $newRoom->id,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('check_in');

    expect($booking->fresh()->room_id)->toBe($booking->room_id);
});
