<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backstop for abandoned public self-service checkouts — see
// RoomAvailabilityService::cancelExpiredHolds() for why walk-in bookings
// are unaffected.
Schedule::command('bookings:cancel-expired-holds')->everyFiveMinutes();

// (assumption, tune later) — confirmed bookings whose check-in date has
// passed with no check-in get marked a no-show.
Schedule::command('bookings:sweep-no-shows')->dailyAt('02:00');
