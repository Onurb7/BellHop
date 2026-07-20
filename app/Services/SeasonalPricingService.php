<?php

namespace App\Services;

use App\Enums\PricingRuleDateKind;
use App\Models\PricingRule;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a room type's flat base_rate_cents into a per-night rate adjusted
 * by whatever active PricingRule rows cover that specific date. A manual
 * rule always overrides templates outright for any date it covers (core
 * or ramp); templates stack additively with each other. See
 * .claude/hotel-booking-plan.md-adjacent planning notes for the confirmed
 * ramp formula and overlap-resolution rules.
 */
class SeasonalPricingService
{
    /**
     * Sums nightlyRateCents() across every night in [checkIn, checkOut) —
     * the direct replacement for a flat `$nights * $base_rate_cents`.
     */
    public function totalRoomChargeCents(RoomType $roomType, Carbon $checkIn, Carbon $checkOut): int
    {
        $rules = $this->activeRules();
        $total = 0;

        for ($cursor = $checkIn->copy(); $cursor->lt($checkOut); $cursor->addDay()) {
            $total += $this->rateForRules($roomType->base_rate_cents, $cursor, $rules);
        }

        return $total;
    }

    /**
     * The effective rate for a single night — used by search results as a
     * "starting from" indicator, not multiplied into a total (rates can
     * vary night to night, so only totalRoomChargeCents() is ever the
     * true charge).
     */
    public function nightlyRateCents(RoomType $roomType, Carbon $date): int
    {
        return $this->rateForRules($roomType->base_rate_cents, $date, $this->activeRules());
    }

    /**
     * Finds the first other active rule whose core-or-ramp window overlaps
     * the given (not-yet-saved) date_range rule's own core-or-ramp window
     * — used by the admin CRUD to warn on save, not by the money math
     * above (which doesn't need to know *why* a date matched, just what
     * percentage results). day_of_week rules (Weekend) never participate,
     * since "every Friday" isn't a date-range concept to overlap against.
     */
    public function findOverlap(PricingRule $candidate, Collection $others): ?PricingRule
    {
        if ($candidate->date_kind !== PricingRuleDateKind::DateRange) {
            return null;
        }

        foreach ($others as $other) {
            if ($other->is($candidate) || $other->date_kind !== PricingRuleDateKind::DateRange) {
                continue;
            }

            if ($this->windowsOverlap($candidate, $other)) {
                return $other;
            }
        }

        return null;
    }

    private function windowsOverlap(PricingRule $a, PricingRule $b): bool
    {
        foreach ($this->rampedInstances($a) as [$aStart, $aEnd]) {
            foreach ($this->rampedInstances($b) as [$bStart, $bEnd]) {
                if ($aStart->lte($bEnd) && $bStart->lte($aEnd)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function rampedInstances(PricingRule $rule): array
    {
        return array_map(
            fn (array $instance) => [
                $instance[0]->copy()->subDays($rule->ramp_in_days),
                $instance[1]->copy()->addDays($rule->ramp_out_days),
            ],
            $this->candidateInstances($rule, $rule->start_date),
        );
    }

    private function activeRules(): Collection
    {
        return PricingRule::where('active', true)->get();
    }

    private function rateForRules(int $baseRateCents, Carbon $date, Collection $rules): int
    {
        $pct = $this->effectivePercentage($date, $rules);

        return (int) round($baseRateCents * (1 + $pct / 100));
    }

    private function effectivePercentage(Carbon $date, Collection $rules): int
    {
        $manualMatches = $rules
            ->where('is_template', false)
            ->map(fn (PricingRule $rule) => ['rule' => $rule, 'pct' => $this->percentageForRule($rule, $date)])
            ->filter(fn (array $match) => $match['pct'] !== 0);

        if ($manualMatches->isNotEmpty()) {
            return $manualMatches->sortByDesc(fn (array $match) => $match['rule']->created_at)->first()['pct'];
        }

        return (int) $rules
            ->where('is_template', true)
            ->sum(fn (PricingRule $rule) => $this->percentageForRule($rule, $date));
    }

    private function percentageForRule(PricingRule $rule, Carbon $date): int
    {
        if ($rule->date_kind === PricingRuleDateKind::DayOfWeek) {
            return in_array($date->dayOfWeek, $rule->days_of_week ?? [], true) ? $rule->percentage : 0;
        }

        foreach ($this->candidateInstances($rule, $date) as [$start, $end]) {
            $pct = $this->percentageForInstance($rule, $start, $end, $date);

            if ($pct !== 0) {
                return $pct;
            }
        }

        return 0;
    }

    /**
     * A non-recurring date_range rule has exactly one instance. A
     * recurring one is realized for the previous/current/next year so a
     * ramp reaching across a year boundary (or a wraparound range like
     * Winter, stored with end_date a year ahead of start_date) is never
     * missed — only the *length* of the stored range is reused, not its
     * absolute stored year.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function candidateInstances(PricingRule $rule, Carbon $date): array
    {
        if (! $rule->recurring) {
            return [[$rule->start_date->copy(), $rule->end_date->copy()]];
        }

        $lengthDays = $rule->start_date->diffInDays($rule->end_date);
        $instances = [];

        foreach ([$date->year - 1, $date->year, $date->year + 1] as $year) {
            $start = $rule->start_date->copy()->setYear($year);
            $instances[] = [$start, $start->copy()->addDays($lengthDays)];
        }

        return $instances;
    }

    private function percentageForInstance(PricingRule $rule, Carbon $start, Carbon $end, Carbon $date): int
    {
        if ($date->between($start, $end)) {
            return $rule->percentage;
        }

        if ($date->lt($start)) {
            $distance = $date->diffInDays($start);

            return $distance <= $rule->ramp_in_days
                ? (int) round($rule->percentage * ($rule->ramp_in_days + 1 - $distance) / ($rule->ramp_in_days + 1))
                : 0;
        }

        $distance = $end->diffInDays($date);

        return $distance <= $rule->ramp_out_days
            ? (int) round($rule->percentage * ($rule->ramp_out_days + 1 - $distance) / ($rule->ramp_out_days + 1))
            : 0;
    }
}
