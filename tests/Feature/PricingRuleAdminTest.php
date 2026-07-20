<?php

use App\Enums\PricingRuleDateKind;
use App\Models\PricingRule;
use App\Models\User;
use App\Support\EasterDateCalculator;
use Spatie\Permission\Models\Role;

function actingAsAdmin(): User
{
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function actingAsStaff(): User
{
    Role::findOrCreate('staff');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    return $staff;
}

function manualPricingRule(array $overrides = []): PricingRule
{
    return PricingRule::create(array_merge([
        'name' => 'Existing Rule',
        'is_template' => false,
        'date_kind' => PricingRuleDateKind::DateRange,
        'recurring' => false,
        'percentage' => 10,
        'ramp_in_days' => 0,
        'ramp_out_days' => 0,
        'active' => true,
    ], $overrides));
}

it('blocks staff from every /admin/pricing route', function () {
    $staff = actingAsStaff();
    $rule = manualPricingRule();

    $this->actingAs($staff)->get('/admin/pricing')->assertForbidden();
    $this->actingAs($staff)->get('/admin/pricing/create')->assertForbidden();
    $this->actingAs($staff)->get("/admin/pricing/{$rule->id}/edit")->assertForbidden();
    $this->actingAs($staff)->post('/admin/pricing', [])->assertForbidden();
    $this->actingAs($staff)->delete("/admin/pricing/{$rule->id}")->assertForbidden();
});

it('lets an admin create a manual pricing rule', function () {
    $admin = actingAsAdmin();

    $this->actingAs($admin)->post('/admin/pricing', [
        'name' => 'Boat Show',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
        'recurring' => false,
        'percentage' => 12,
        'ramp_in_days' => 1,
        'ramp_out_days' => 1,
        'active' => true,
    ])->assertRedirect('/admin/pricing');

    $rule = PricingRule::where('name', 'Boat Show')->first();
    expect($rule)->not->toBeNull()
        ->and($rule->is_template)->toBeFalse()
        ->and($rule->date_kind)->toBe(PricingRuleDateKind::DateRange);
});

it('refuses to delete a template', function () {
    $admin = actingAsAdmin();
    $template = PricingRule::create([
        'name' => 'Weekend', 'is_template' => true, 'template_key' => 'weekend',
        'date_kind' => PricingRuleDateKind::DayOfWeek, 'days_of_week' => [5, 6],
        'percentage' => 10, 'active' => false,
    ]);

    $this->actingAs($admin)->delete("/admin/pricing/{$template->id}")->assertStatus(422);

    expect(PricingRule::find($template->id))->not->toBeNull();
});

it('never lets a template update change its name or date_kind, even if submitted', function () {
    $admin = actingAsAdmin();
    $template = PricingRule::create([
        'name' => 'Christmas', 'is_template' => true, 'template_key' => 'christmas',
        'date_kind' => PricingRuleDateKind::DateRange, 'recurring' => true,
        'start_date' => '2026-12-25', 'end_date' => '2026-12-25',
        'percentage' => 20, 'ramp_in_days' => 2, 'ramp_out_days' => 1, 'active' => false,
    ]);

    $this->actingAs($admin)->put("/admin/pricing/{$template->id}", [
        'name' => 'Hacked Name',
        'start_date' => '2026-12-25',
        'end_date' => '2026-12-25',
        'recurring' => true,
        'percentage' => 30,
        'active' => true,
    ])->assertRedirect('/admin/pricing');

    $template->refresh();
    expect($template->name)->toBe('Christmas')
        ->and($template->date_kind)->toBe(PricingRuleDateKind::DateRange)
        ->and($template->percentage)->toBe(30) // tunable fields DO change
        ->and($template->active)->toBeTrue();
});

it('flashes a warning naming the template when a new manual rule overlaps an active one', function () {
    $admin = actingAsAdmin();
    PricingRule::create([
        'name' => 'Christmas', 'is_template' => true, 'template_key' => 'christmas',
        'date_kind' => PricingRuleDateKind::DateRange, 'recurring' => true,
        'start_date' => '2026-12-25', 'end_date' => '2026-12-25',
        'percentage' => 20, 'active' => true,
    ]);

    $response = $this->actingAs($admin)->from('/admin/pricing/create')->post('/admin/pricing', [
        'name' => 'Holiday Special',
        'start_date' => '2026-12-24',
        'end_date' => '2026-12-26',
        'recurring' => false,
        'percentage' => 5,
        'active' => true,
    ]);

    $response->assertRedirect('/admin/pricing');
    expect(session('warning'))->toContain('Christmas');
});

it('flashes a warning when a new manual rule overlaps another active manual rule', function () {
    $admin = actingAsAdmin();
    manualPricingRule(['name' => 'Existing Event', 'start_date' => '2026-07-01', 'end_date' => '2026-07-10']);

    $response = $this->actingAs($admin)->post('/admin/pricing', [
        'name' => 'Overlapping Event',
        'start_date' => '2026-07-05',
        'end_date' => '2026-07-15',
        'recurring' => false,
        'percentage' => 8,
        'active' => true,
    ]);

    $response->assertRedirect('/admin/pricing');
    expect(session('warning'))->toContain('Existing Event');
});

it('does not warn when a new manual rule does not overlap anything active', function () {
    $admin = actingAsAdmin();
    manualPricingRule(['name' => 'Far Away Event', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05']);

    $response = $this->actingAs($admin)->post('/admin/pricing', [
        'name' => 'Unrelated Event',
        'start_date' => '2026-07-05',
        'end_date' => '2026-07-15',
        'recurring' => false,
        'percentage' => 8,
        'active' => true,
    ]);

    $response->assertRedirect('/admin/pricing');
    expect(session('warning'))->toBeNull();
});

it('refreshes the Easter template date to the current year via the scheduled command', function () {
    $easter = PricingRule::create([
        'name' => 'Easter', 'is_template' => true, 'template_key' => 'easter',
        'date_kind' => PricingRuleDateKind::DateRange, 'recurring' => false,
        'start_date' => '2000-01-01', 'end_date' => '2000-01-01',
        'percentage' => 15, 'active' => false,
    ]);

    $this->artisan('pricing:refresh-computed-dates')->assertSuccessful();

    $expected = EasterDateCalculator::forYear(now()->year);
    $easter->refresh();
    expect($easter->start_date->toDateString())->toBe($expected->toDateString())
        ->and($easter->end_date->toDateString())->toBe($expected->toDateString());
});
