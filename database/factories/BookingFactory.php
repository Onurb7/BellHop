<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('-5 days', '+20 days');
        $nights = fake()->numberBetween(1, 6);

        return [
            'room_id' => Room::factory(),
            'guest_id' => Guest::factory(),
            'check_in' => $checkIn,
            'check_out' => (clone $checkIn)->modify("+{$nights} days"),
            'status' => BookingStatus::Confirmed,
        ];
    }
}
