<?php

use App\Enums\BookingChargeCategory;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;

it('converts a non-USD room type price to USD when the room charge is created', function () {
    // EUR: 0.5 means $1 = €0.5, so a EUR amount converts to USD by
    // dividing by 0.5 (i.e. doubling).
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['rates' => ['EUR' => 0.5]]),
    ]);

    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000, 'currency' => 'EUR']);
    $room = Room::factory()->create(['room_type_id' => $roomType->id]);
    $booking = Booking::factory()->create([
        'room_id' => $room->id,
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(15),
        'check_in' => today()->addDays(10),
        'check_out' => today()->addDays(12),
    ]);

    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.test',
        'phone' => null,
        'address' => null,
    ])->assertRedirect(route('booking.show', $booking));

    $charge = $booking->fresh()->charges()->where('category', BookingChargeCategory::Room)->first();

    // 2 nights * €100.00/night = €200.00 -> $400.00 (divide by the 0.5 rate)
    expect($charge->amount_cents)->toBe(40000);
});
