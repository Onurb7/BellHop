<?php

namespace App\Services;

use App\Enums\Currency;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    /**
     * Rates relative to $base (e.g. base=USD gives ['EUR' => 0.92, ...]).
     * Only successful responses get cached — a transient failure isn't
     * "stuck" returning null for the rest of the day, the next request
     * just tries again.
     */
    public function latest(string $base = 'USD'): ?array
    {
        $cacheKey = "exchange-rates:{$base}";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $symbols = collect(Currency::cases())
            ->pluck('value')
            ->reject(fn (string $code) => $code === $base)
            ->implode(',');

        try {
            $response = Http::timeout(5)->get(config('services.frankfurter.base_url').'/latest', [
                'base' => $base,
                'symbols' => $symbols,
            ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $rates = $response->json('rates');
        Cache::put($cacheKey, $rates, now()->addDay());

        return $rates;
    }

    /**
     * Converts via a USD pivot — Frankfurter only ever gives base=USD
     * rates, so any non-USD-to-non-USD conversion goes through USD as an
     * intermediate step. Fails open (returns the amount unconverted) if
     * rates are unavailable — a booking must never be blocked by a 3rd-
     * party outage. Rare in practice (24h cache + daily pre-warm job),
     * but a real gap: a booking made during a genuine multi-day outage
     * would under/overcharge a non-USD-priced room. Accepted tradeoff.
     */
    public function convertCents(int $amountCents, string $from, string $to): int
    {
        if ($from === $to) {
            return $amountCents;
        }

        $rates = $this->latest();

        if (! $rates) {
            return $amountCents;
        }

        $usdCents = $from === 'USD' ? $amountCents : $amountCents / ($rates[$from] ?? 1);
        $targetCents = $to === 'USD' ? $usdCents : $usdCents * ($rates[$to] ?? 1);

        return (int) round($targetCents);
    }
}
