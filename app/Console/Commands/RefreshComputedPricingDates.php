<?php

namespace App\Console\Commands;

use App\Models\PricingRule;
use App\Support\EasterDateCalculator;
use Illuminate\Console\Command;

/**
 * Yearly refresh for any pricing-rule template whose date isn't fixed on
 * the calendar. Easter is the only one today — written as a small list
 * rather than Easter hardcoded inline so a future computed-annually date
 * can be added here instead of spawning a new scheduled command.
 */
class RefreshComputedPricingDates extends Command
{
    protected $signature = 'pricing:refresh-computed-dates';

    protected $description = 'Recompute the calendar date for pricing-rule templates whose date moves every year (e.g. Easter)';

    public function handle(): int
    {
        $year = now()->year;

        $easter = PricingRule::where('template_key', 'easter')->first();

        if ($easter) {
            $easterDate = EasterDateCalculator::forYear($year);
            $easter->update(['start_date' => $easterDate, 'end_date' => $easterDate]);
            $this->info("Easter {$year}: {$easterDate->toDateString()}");
        }

        return self::SUCCESS;
    }
}
