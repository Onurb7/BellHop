<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo login switcher
    |--------------------------------------------------------------------------
    |
    | Controls the one-click "log in as..." buttons on the login page, so
    | recruiters/interviewers can preview role dashboards without real
    | credentials. `accounts` is the whitelist consumed by the login-as
    | route/controller — super-admin is intentionally not a key here, so it
    | is structurally unreachable through that code path, not just
    | filtered out by a runtime check.
    |
    */

    'login_enabled' => (bool) env('DEMO_LOGIN_ENABLED', true),

    // Guards app/Console/Commands/ReseedDemoActivity.php — the monthly
    // wipe-and-regenerate of guests/bookings. Off switch for a genuinely
    // destructive scheduled operation, matching the login_enabled precedent.
    'reseed_activity_enabled' => (bool) env('DEMO_RESEED_ACTIVITY_ENABLED', true),

    // Seeded, but deliberately outside `accounts` below — never reachable
    // via the login-as route.
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'super.admin@bellhop.test'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'accounts' => [
        'admin' => [
            'email' => env('DEMO_ADMIN_EMAIL', 'admin@bellhop.test'),
            'password' => env('DEMO_ADMIN_PASSWORD'),
        ],
        'staff' => [
            'email' => env('DEMO_STAFF_EMAIL', 'staff@bellhop.test'),
            'password' => env('DEMO_STAFF_PASSWORD'),
        ],
        'guest' => [
            'email' => env('DEMO_GUEST_EMAIL', 'guest@bellhop.test'),
            'password' => env('DEMO_GUEST_PASSWORD'),
        ],
    ],

];
