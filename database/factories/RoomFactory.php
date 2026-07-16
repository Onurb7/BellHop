<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'title' => fake()->words(3, true),
            'number' => (string) fake()->unique()->numberBetween(100, 599),
            'floor' => (string) fake()->numberBetween(1, 5),
            'status' => RoomStatus::Active,
            'is_published' => true,
        ];
    }
}
