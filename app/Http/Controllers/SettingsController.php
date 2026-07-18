<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Enums\WeekStart;
use App\Http\Requests\SettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Edit', [
            'date_format' => $user->getSetting('date_format', DateFormat::Iso->value),
            'time_format' => $user->getSetting('time_format', TimeFormat::TwentyFourHour->value),
            'week_start' => $user->getSetting('week_start', WeekStart::Monday->value),
            'currency' => $user->getSetting('currency', Currency::Usd->value),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            $request->user()->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Settings updated.');
    }
}
