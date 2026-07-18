<?php

namespace App\Http\Middleware;

use App\Enums\Currency;
use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Enums\WeekStart;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'name', 'email', 'roles'),
                    'date_format' => $user->getSetting('date_format', DateFormat::Iso->value),
                    'time_format' => $user->getSetting('time_format', TimeFormat::TwentyFourHour->value),
                    'week_start' => $user->getSetting('week_start', WeekStart::Monday->value),
                    'currency' => $user->getSetting('currency', Currency::Usd->value),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            // Almost always a cache hit (24h TTL + daily pre-warm job), so
            // this adds no real latency to every request. Null if the FX
            // API is unreachable — useMoney() degrades to showing origin
            // amounts unconverted rather than erroring.
            'exchange_rates' => app(ExchangeRateService::class)->latest(),
        ];
    }
}
