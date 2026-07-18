<?php

use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Http;

it('fetches and caches exchange rates, hitting the API only once', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['rates' => ['EUR' => 0.92, 'JPY' => 157.3]]),
    ]);

    $service = new ExchangeRateService;

    $first = $service->latest();
    $second = $service->latest();

    expect($first)->toBe(['EUR' => 0.92, 'JPY' => 157.3])
        ->and($second)->toBe($first);
    Http::assertSentCount(1);
});

it('returns null and caches nothing when the API fails', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(null, 500),
    ]);

    $service = new ExchangeRateService;

    expect($service->latest())->toBeNull();

    // A follow-up call retries rather than returning the same cached null.
    $service->latest();
    Http::assertSentCount(2);
});

it('converts cents through a USD pivot in both directions and cross-currency', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['rates' => ['EUR' => 0.5, 'JPY' => 100.0]]),
    ]);

    $service = new ExchangeRateService;

    expect($service->convertCents(1000, 'USD', 'USD'))->toBe(1000)
        ->and($service->convertCents(1000, 'USD', 'EUR'))->toBe(500)
        ->and($service->convertCents(500, 'EUR', 'USD'))->toBe(1000)
        ->and($service->convertCents(1000, 'EUR', 'JPY'))->toBe(200000);
});

it('fails open and returns the original amount when converting without rates available', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(null, 500),
    ]);

    $service = new ExchangeRateService;

    expect($service->convertCents(1500, 'EUR', 'USD'))->toBe(1500);
});
