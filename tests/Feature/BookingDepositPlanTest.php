<?php

use App\Enums\BookingChargeCategory;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\RoomAvailabilityService;

/**
 * No Inertia testing helpers are installed in this project (no other test
 * asserts on page props, only on resulting DB/redirect state) — scrapes
 * the same embedded `data-page` JSON blob a real browser hydrates from.
 */
function inertiaProps(\Illuminate\Testing\TestResponse $response): array
{
    preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $matches);

    return json_decode($matches[1], true)['props'];
}

it('treats a booking as a deposit plan only when the deposit is less than the room charge, ignoring service charges', function () {
    $booking = Booking::factory()->create(['deposit_cents' => 30000]);
    $booking->charges()->create([
        'category' => BookingChargeCategory::Room,
        'description' => 'Room charge',
        'amount_cents' => 100000,
    ]);

    expect($booking->fresh()->isDepositPlan())->toBeTrue();
});

it('is never fooled into looking like a deposit plan by a service charge on top of a fully-paid room', function () {
    $booking = Booking::factory()->create(['deposit_cents' => 100000]);
    $booking->charges()->create([
        'category' => BookingChargeCategory::Room,
        'description' => 'Room charge',
        'amount_cents' => 100000,
    ]);
    $booking->charges()->create([
        'category' => BookingChargeCategory::Service,
        'description' => 'Breakfast — 2 night(s)',
        'amount_cents' => 4000,
    ]);

    expect($booking->fresh()->isDepositPlan())->toBeFalse();
});

it('previews full payment (not a 30% deposit) for a booking within 3 days of check-in, matching what storeGuest() actually charges', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000, 'currency' => 'USD']);
    $room = Room::factory()->create(['room_type_id' => $roomType->id]);

    $booking = app(RoomAvailabilityService::class)->lock(
        $room->id,
        today(),
        today()->addDay(),
    );

    $props = inertiaProps($this->get("/book/{$booking->id}"));

    expect($props['booking']['is_deposit_plan'])->toBeFalse()
        ->and($props['booking']['deposit_cents'])->toBe(10000)
        ->and($props['booking']['deposit_cents'])->toBe($props['booking']['total_cents']);

    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.fullpay@example.test',
    ]);

    expect($booking->fresh()->deposit_cents)->toBe(10000);
});

it('still previews a 30% deposit for a booking more than 3 days out', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000, 'currency' => 'USD']);
    $room = Room::factory()->create(['room_type_id' => $roomType->id]);

    $booking = app(RoomAvailabilityService::class)->lock(
        $room->id,
        today()->addDays(10),
        today()->addDays(12),
    );

    $props = inertiaProps($this->get("/book/{$booking->id}"));

    expect($props['booking']['is_deposit_plan'])->toBeTrue()
        ->and($props['booking']['deposit_cents'])->toBe(6000);
});
