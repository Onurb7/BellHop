<?php

namespace App\Console\Commands;

use App\Services\DemoActivitySeeder;
use Illuminate\Console\Command;

class ReseedDemoActivity extends Command
{
    protected $signature = 'demo:reseed-activity';

    protected $description = 'Wipe and regenerate demo guests/bookings so the deployed app always shows a fresh, today-anchored spread of activity';

    /**
     * Deletes ALL rows in guests/bookings indiscriminately — correct for
     * this portfolio demo, but there's no is_demo flag distinguishing
     * seeded rows from real ones. Must never run against an environment
     * with genuine user bookings.
     */
    public function handle(DemoActivitySeeder $seeder): int
    {
        if (! config('demo.reseed_activity_enabled')) {
            $this->info('Skipped — DEMO_RESEED_ACTIVITY_ENABLED is false.');

            return self::SUCCESS;
        }

        $seeder->reseedGuestsAndBookings();

        $this->info('Reseeded demo guests and bookings.');

        return self::SUCCESS;
    }
}
