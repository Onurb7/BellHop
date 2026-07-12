<?php

namespace Database\Factories;

use App\Enums\ServicePricingType;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Parking', 'Breakfast', 'Late Checkout', 'Airport Transfer', 'Spa Access',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'description' => fake()->sentence(),
            'unit_price_cents' => fake()->numberBetween(1000, 9000),
            'pricing_type' => fake()->randomElement(ServicePricingType::cases()),
            'active' => true,
        ];
    }
}
