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

// Off-session charge for deposit-plan bookings whose balance_due_at
// (check-in minus 3 days) has arrived; without a saved card, sends a
// payment reminder instead.
Schedule::command('bookings:charge-due-balances')->dailyAt('03:00');

// 24h grace window after a charge decline or a no-saved-card reminder —
// run after the job above, not concurrently with it.
Schedule::command('bookings:cancel-unpaid-balances')->dailyAt('04:00');

// Checked-out bookings with a balance still owed (e.g. incidentals) never
// auto-cancel — the stay already happened — so this just nudges the guest
// once a day until it's settled, same PaymentReminderMail staff can also
// send manually from the reservation page.
Schedule::command('bookings:remind-checked-out-balances')->dailyAt('08:00');

// Monthly refresh of demo guests/bookings so a recruiter demoing the app
// always sees a plausible, "today"-anchored spread of activity — rooms,
// room types, amenities and services (and their images) are permanently
// static and untouched by this. Disable via DEMO_RESEED_ACTIVITY_ENABLED=false.
Schedule::command('demo:reseed-activity')->monthlyOn(1, '01:00');

// Keeps the exchange-rate cache warm so no page load ever blocks on a live
// API call — cache TTL is 24h, this runs well inside that window.
Schedule::command('exchange-rates:refresh')->dailyAt('01:15');

// Recomputes the Easter pricing-rule template's date for the new year —
// Easter moves every year, unlike Christmas/New Year's/Summer/Winter.
Schedule::command('pricing:refresh-computed-dates')->yearlyOn(1, 1, '00:30');
