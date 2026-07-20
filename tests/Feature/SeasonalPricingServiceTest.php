<?php

use App\Enums\PricingRuleDateKind;
use App\Models\PricingRule;
use App\Models\RoomType;
use App\Services\SeasonalPricingService;
use Carbon\Carbon;

function makeRule(array $overrides = []): PricingRule
{
    return PricingRule::create(array_merge([
        'name' => 'Test Rule',
        'is_template' => false,
        'date_kind' => PricingRuleDateKind::DateRange,
        'recurring' => false,
        'percentage' => 10,
        'ramp_in_days' => 0,
        'ramp_out_days' => 0,
        'active' => true,
    ], $overrides));
}

it('returns the base rate unchanged when no rules are active', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);

    $rate = app(SeasonalPricingService::class)->nightlyRateCents($roomType, Carbon::parse('2026-07-12'));

    expect($rate)->toBe(10000);
});

it('applies the full percentage on a core date_range date', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule(['start_date' => '2026-07-10', 'end_date' => '2026-07-14', 'percentage' => 10]);

    $rate = app(SeasonalPricingService::class)->nightlyRateCents($roomType, Carbon::parse('2026-07-12'));

    expect($rate)->toBe(11000);
});

it('ramps in and out per the confirmed formula, matching the worked example exactly', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule([
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-14',
        'percentage' => 10,
        'ramp_in_days' => 2,
        'ramp_out_days' => 2,
    ]);

    $service = app(SeasonalPricingService::class);
    $rateOn = fn (string $date) => $service->nightlyRateCents($roomType, Carbon::parse($date));

    expect($rateOn('2026-07-07'))->toBe(10000)  // 3 days out — no effect
        ->and($rateOn('2026-07-08'))->toBe(10300) // 2 days out — 3%
        ->and($rateOn('2026-07-09'))->toBe(10700) // 1 day out — 7%
        ->and($rateOn('2026-07-10'))->toBe(11000) // core
        ->and($rateOn('2026-07-14'))->toBe(11000) // core
        ->and($rateOn('2026-07-15'))->toBe(10700) // 1 day after — 7%
        ->and($rateOn('2026-07-16'))->toBe(10300) // 2 days after — 3%
        ->and($rateOn('2026-07-17'))->toBe(10000); // back to normal
});

it('lets a ramp side be independently skipped by setting it to 0', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule([
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-14',
        'percentage' => 10,
        'ramp_in_days' => 2,
        'ramp_out_days' => 0,
    ]);

    $service = app(SeasonalPricingService::class);

    expect($service->nightlyRateCents($roomType, Carbon::parse('2026-07-08')))->toBe(10300)
        ->and($service->nightlyRateCents($roomType, Carbon::parse('2026-07-15')))->toBe(10000);
});

it('stacks two active templates additively rather than compounding', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule(['is_template' => true, 'template_key' => 'winter', 'recurring' => true, 'start_date' => '2026-12-01', 'end_date' => '2027-02-28', 'percentage' => 10]);
    makeRule(['is_template' => true, 'template_key' => 'christmas', 'recurring' => true, 'start_date' => '2026-12-25', 'end_date' => '2026-12-25', 'percentage' => 10]);

    $rate = app(SeasonalPricingService::class)->nightlyRateCents($roomType, Carbon::parse('2026-12-25'));

    // Additive: 10000 * 1.20 = 12000, not 10000 * 1.1 * 1.1 = 12100.
    expect($rate)->toBe(12000);
});

it('lets a manual rule override an overlapping template outright, without stacking', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule(['is_template' => true, 'template_key' => 'christmas', 'recurring' => true, 'start_date' => '2026-12-25', 'end_date' => '2026-12-25', 'percentage' => 20]);
    makeRule(['is_template' => false, 'start_date' => '2026-12-25', 'end_date' => '2026-12-25', 'percentage' => 5]);

    $rate = app(SeasonalPricingService::class)->nightlyRateCents($roomType, Carbon::parse('2026-12-25'));

    expect($rate)->toBe(10500);
});

it('lets the most recently created manual rule win when two manual rules overlap', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    $older = makeRule(['start_date' => '2026-07-01', 'end_date' => '2026-07-10', 'percentage' => 10]);
    $older->forceFill(['created_at' => now()->subDay()])->save();
    makeRule(['start_date' => '2026-07-05', 'end_date' => '2026-07-15', 'percentage' => 25]);

    $rate = app(SeasonalPricingService::class)->nightlyRateCents($roomType, Carbon::parse('2026-07-07'));

    expect($rate)->toBe(12500);
});

it('matches a wraparound recurring range (Winter, Dec-Feb) in both December and the following January', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule(['is_template' => true, 'template_key' => 'winter', 'recurring' => true, 'start_date' => '2026-12-01', 'end_date' => '2027-02-28', 'percentage' => -10]);

    $service = app(SeasonalPricingService::class);

    expect($service->nightlyRateCents($roomType, Carbon::parse('2026-12-15')))->toBe(9000)
        ->and($service->nightlyRateCents($roomType, Carbon::parse('2027-01-15')))->toBe(9000)
        ->and($service->nightlyRateCents($roomType, Carbon::parse('2027-06-15')))->toBe(10000);
});

it('ignores an inactive rule regardless of date match', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule(['start_date' => '2026-07-10', 'end_date' => '2026-07-14', 'percentage' => 50, 'active' => false]);

    $rate = app(SeasonalPricingService::class)->nightlyRateCents($roomType, Carbon::parse('2026-07-12'));

    expect($rate)->toBe(10000);
});

it('sums totalRoomChargeCents() across every night of a stay, not a flat nights multiplication', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000]);
    makeRule(['start_date' => '2026-07-12', 'end_date' => '2026-07-12', 'percentage' => 10]);

    // 3 nights: 07-11 (normal), 07-12 (core +10%), 07-13 (normal) — checkout excluded.
    $total = app(SeasonalPricingService::class)->totalRoomChargeCents(
        $roomType,
        Carbon::parse('2026-07-11'),
        Carbon::parse('2026-07-14'),
    );

    expect($total)->toBe(10000 + 11000 + 10000);
});

it('finds an overlap between two date_range rules, including through their ramp windows', function () {
    $existing = makeRule(['start_date' => '2026-07-10', 'end_date' => '2026-07-14', 'ramp_out_days' => 2]);
    $candidate = makeRule(['start_date' => '2026-07-16', 'end_date' => '2026-07-20', 'ramp_in_days' => 0]);

    $overlap = app(SeasonalPricingService::class)->findOverlap($candidate, collect([$existing, $candidate]));

    expect($overlap)->not->toBeNull()->and($overlap->is($existing))->toBeTrue();
});

it('finds no overlap between two date_range rules with a real gap between them', function () {
    $existing = makeRule(['start_date' => '2026-07-01', 'end_date' => '2026-07-05']);
    $candidate = makeRule(['start_date' => '2026-08-01', 'end_date' => '2026-08-05']);

    $overlap = app(SeasonalPricingService::class)->findOverlap($candidate, collect([$existing, $candidate]));

    expect($overlap)->toBeNull();
});

it('never treats the Weekend day_of_week template as participating in date-range overlap checks', function () {
    $weekend = makeRule(['is_template' => true, 'template_key' => 'weekend', 'date_kind' => PricingRuleDateKind::DayOfWeek, 'days_of_week' => [5, 6]]);
    $manual = makeRule(['start_date' => '2026-07-10', 'end_date' => '2026-07-14']);

    $overlap = app(SeasonalPricingService::class)->findOverlap($manual, collect([$weekend, $manual]));

    expect($overlap)->toBeNull();
});
