<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class RefreshExchangeRates extends Command
{
    protected $signature = 'exchange-rates:refresh';

    protected $description = 'Pre-warm the cached USD exchange rates so pages never block on a live API call';

    public function handle(ExchangeRateService $rates): int
    {
        $result = $rates->latest();

        $this->info($result ? 'Exchange rates refreshed.' : 'Exchange rate API unreachable — kept serving the existing cache.');

        return self::SUCCESS;
    }
}
