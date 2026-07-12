<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Classic Room', 'Deluxe Room', 'Executive Suite', 'Garden Suite', 'Penthouse Suite',
        ]).' '.fake()->unique()->numerify('##');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'base_rate_cents' => fake()->numberBetween(8000, 45000),
            'max_occupancy' => fake()->numberBetween(1, 4),
        ];
    }
}
