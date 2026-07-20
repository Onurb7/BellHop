<?php

namespace Database\Seeders;

use App\Enums\PricingRuleDateKind;
use App\Models\PricingRule;
use App\Support\EasterDateCalculator;
use Illuminate\Database\Seeder;

/**
 * Seeds the 6 fixed pricing-rule templates by template_key. Deliberately
 * `firstOrCreate`, not `updateOrCreate` like RoleAndDemoUserSeeder's
 * identity-seeding — once a template row exists, an admin owns its
 * percentage/ramp/active/dates, and a re-run (e.g. after a fresh deploy)
 * must never clobber their tuning back to these starter values. All seed
 * inactive with placeholder percentages: a fresh deploy must never
 * silently start charging more until an admin deliberately opts in. The
 * one exception is Easter's date, refreshed unconditionally below —
 * mirrors what the scheduled pricing:refresh-computed-dates job does, so
 * a re-seed never leaves it stale, without touching anything admin-owned.
 */
class PricingRuleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;
        $easterDate = EasterDateCalculator::forYear($year);

        $templates = [
            [
                'template_key' => 'weekend',
                'name' => 'Weekend',
                'date_kind' => PricingRuleDateKind::DayOfWeek,
                'days_of_week' => [5, 6], // Fri, Sat nights
                'percentage' => 10,
                'ramp_in_days' => 0,
                'ramp_out_days' => 0,
            ],
            [
                'template_key' => 'christmas',
                'name' => 'Christmas',
                'date_kind' => PricingRuleDateKind::DateRange,
                'recurring' => true,
                'start_date' => sprintf('%d-12-25', $year),
                'end_date' => sprintf('%d-12-25', $year),
                'percentage' => 20,
                'ramp_in_days' => 2,
                'ramp_out_days' => 1,
            ],
            [
                'template_key' => 'easter',
                'name' => 'Easter',
                'date_kind' => PricingRuleDateKind::DateRange,
                'recurring' => false, // kept current by pricing:refresh-computed-dates
                'start_date' => $easterDate,
                'end_date' => $easterDate,
                'percentage' => 15,
                'ramp_in_days' => 1,
                'ramp_out_days' => 1,
            ],
            [
                'template_key' => 'new_years',
                'name' => "New Year's",
                'date_kind' => PricingRuleDateKind::DateRange,
                'recurring' => true,
                'start_date' => sprintf('%d-12-31', $year),
                'end_date' => sprintf('%d-01-01', $year + 1),
                'percentage' => 25,
                'ramp_in_days' => 1,
                'ramp_out_days' => 1,
            ],
            [
                'template_key' => 'summer',
                'name' => 'Summer',
                'date_kind' => PricingRuleDateKind::DateRange,
                'recurring' => true,
                'start_date' => sprintf('%d-06-01', $year),
                'end_date' => sprintf('%d-08-31', $year),
                'percentage' => 15,
                'ramp_in_days' => 3,
                'ramp_out_days' => 3,
            ],
            [
                'template_key' => 'winter',
                'name' => 'Winter',
                'date_kind' => PricingRuleDateKind::DateRange,
                'recurring' => true,
                'start_date' => sprintf('%d-12-01', $year),
                'end_date' => sprintf('%d-02-28', $year + 1),
                'percentage' => -10,
                'ramp_in_days' => 3,
                'ramp_out_days' => 3,
            ],
        ];

        foreach ($templates as $template) {
            $key = $template['template_key'];

            PricingRule::firstOrCreate(
                ['template_key' => $key],
                array_merge(['is_template' => true, 'active' => false], $template),
            );

            if ($key === 'easter') {
                PricingRule::where('template_key', 'easter')
                    ->update(['start_date' => $easterDate, 'end_date' => $easterDate]);
            }
        }
    }
}
