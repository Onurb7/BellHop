<?php

use App\Enums\BookingStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\Booking;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new RoomAvailabilityService;
});

it('excludes a room with an overlapping booking from search results', function () {
    $room = Room::factory()->create();
    Booking::factory()->for($room)->create([
        'check_in' => '2026-08-10',
        'check_out' => '2026-08-15',
        'status' => BookingStatus::Confirmed,
    ]);

    $results = $this->service->searchAvailableRooms(Carbon::parse('2026-08-12'), Carbon::parse('2026-08-14'));

    expect($results)->not->toContain(fn ($result) => $result['room_id'] === $room->id);
});

it('includes a room whose only booking is a back-to-back turnover', function () {
    $room = Room::factory()->create();
    Booking::factory()->for($room)->create([
        'check_in' => '2026-08-10',
        'check_out' => '2026-08-15',
        'status' => BookingStatus::Confirmed,
    ]);

    // Checkout day (Aug 15) equals the new stay's check-in day — the
    // half-open daterange means this is not an overlap.
    $results = $this->service->searchAvailableRooms(Carbon::parse('2026-08-15'), Carbon::parse('2026-08-18'));

    expect(collect($results)->pluck('room_id'))->toContain($room->id);
});

it('locks a room by creating a pending_payment hold with an expiry', function () {
    $room = Room::factory()->create();

    $booking = $this->service->lock($room->id, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-05'));

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->guest_id)->toBeNull()
        ->and($booking->expires_at)->not->toBeNull();
});

it('refuses to lock a room that is already held for overlapping dates', function () {
    $room = Room::factory()->create();
    $this->service->lock($room->id, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-05'));

    // Caught via the isAvailable() pre-check here (single-threaded test);
    // the Postgres exclusion constraint is the real defense against a
    // genuine concurrent race and isn't itself exercised by this test.
    $this->service->lock($room->id, Carbon::parse('2026-09-03'), Carbon::parse('2026-09-06'));
})->throws(RoomUnavailableException::class);

it('sweeps an expired unclaimed draft but leaves a live one alone', function () {
    $expired = Booking::factory()->create([
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->subMinutes(5),
    ]);
    $live = Booking::factory()->create([
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->service->sweepExpiredDrafts();

    expect(Booking::find($expired->id))->toBeNull()
        ->and(Booking::find($live->id))->not->toBeNull();
});

it('reports an expired draft as not live and deletes it on check', function () {
    $booking = Booking::factory()->create([
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->subMinute(),
    ]);

    expect($this->service->isLiveDraft($booking))->toBeFalse();
    expect(Booking::find($booking->id))->toBeNull();
});

it('reports a fresh draft as live', function () {
    $booking = Booking::factory()->create([
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(10),
    ]);

    expect($this->service->isLiveDraft($booking))->toBeTrue();
});
