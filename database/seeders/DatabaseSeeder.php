<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndDemoUserSeeder::class);
        // Templates must exist before DemoHotelSeeder's first run, which
        // activates a couple of them by template_key for demo visibility.
        $this->call(PricingRuleTemplateSeeder::class);
        $this->call(DemoHotelSeeder::class);
    }
}
