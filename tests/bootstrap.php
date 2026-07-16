<?php

/**
 * A custom bootstrap, not just `vendor/autoload.php` — docker-compose's
 * `env_file: .env` bakes every `.env` key into the `app` container's real
 * OS environment at container-creation time (see .claude/CLAUDE.md). Since
 * Laravel's env() helper resolves against $_SERVER before $_ENV/getenv(),
 * and PHPUnit's `<env force="true">` XML config only ever touches
 * $_ENV/putenv(), any key already present in `.env` (which is most of
 * them) silently keeps its real dev-container value no matter what
 * phpunit.xml says. Setting $_SERVER directly here, before Laravel's own
 * bootstrap ever runs, is the only override that actually sticks.
 */
$testEnv = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    // pgsql, not sqlite: the bookings table's exclusion constraint (see
    // create_bookings_table migration) is raw Postgres SQL and cannot be
    // created under sqlite. Host/user/password fall through to the
    // container's real env (same as dev) — only the connection and
    // database name are overridden. Requires a one-time
    // `docker compose exec postgres createdb -U bellhop bellhop_testing`
    // per machine, see .claude/CLAUDE.md.
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'bellhop_testing',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
    // A fixed, known secret (not the real one baked into the container)
    // so tests can sign their own Stripe-Signature headers.
    'STRIPE_WEBHOOK_SECRET' => 'whsec_test_secret',
];

foreach ($testEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
