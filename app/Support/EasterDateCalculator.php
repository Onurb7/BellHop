<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Standalone so both PricingRuleTemplateSeeder (initial seed) and
 * RefreshComputedPricingDates (the annual scheduled job) share the exact
 * same computation rather than duplicating it.
 */
class EasterDateCalculator
{
    /**
     * The Anonymous Gregorian algorithm (Meeus/Jones/Butcher) — verified
     * against known Easter Sundays (2023-04-09, 2024-03-31, 2025-04-20,
     * 2026-04-05, 2027-03-28, 2000-04-23, 2016-03-27). PHP's ext-calendar
     * `easter_date()` isn't installed in this image, so this is
     * implemented directly rather than adding the extension for one date.
     */
    public static function forYear(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }
}
