<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PricingRuleRequest;
use App\Models\PricingRule;
use App\Services\SeasonalPricingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PricingRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pricing/Index', [
            'rules' => PricingRule::orderBy('is_template', 'desc')->orderBy('name')->get()->map(fn (PricingRule $rule) => $this->forDisplay($rule)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pricing/Form', [
            'rule' => null,
        ]);
    }

    public function store(PricingRuleRequest $request, SeasonalPricingService $pricing): RedirectResponse
    {
        $rule = PricingRule::create($request->validatedForModel());

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Pricing rule created.')
            ->with('warning', $this->overlapWarning($rule, $pricing));
    }

    public function edit(PricingRule $pricingRule): Response
    {
        return Inertia::render('Admin/Pricing/Form', [
            'rule' => $this->forDisplay($pricingRule),
        ]);
    }

    public function update(PricingRuleRequest $request, PricingRule $pricingRule, SeasonalPricingService $pricing): RedirectResponse
    {
        $pricingRule->update($request->validatedForModel($pricingRule));

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Pricing rule updated.')
            ->with('warning', $this->overlapWarning($pricingRule->fresh(), $pricing));
    }

    public function destroy(PricingRule $pricingRule): RedirectResponse
    {
        abort_if($pricingRule->is_template, 422, 'A template can\'t be deleted — turn it off instead.');

        $pricingRule->delete();

        return redirect()->route('admin.pricing.index')->with('success', 'Pricing rule deleted.');
    }

    /**
     * Advisory only — the resolution itself (manual beats template
     * outright; most-recently-created manual rule wins over another
     * manual rule) is already deterministic in SeasonalPricingService,
     * this just tells the admin their save is about to suppress
     * something else for the overlapping dates.
     */
    private function overlapWarning(PricingRule $rule, SeasonalPricingService $pricing): ?string
    {
        if (! $rule->active) {
            return null;
        }

        $others = PricingRule::where('active', true)->where('id', '!=', $rule->id)->get();
        $overlap = $pricing->findOverlap($rule, $others);

        if (! $overlap) {
            return null;
        }

        $kind = $overlap->is_template ? 'template' : 'manual rule';

        return "This rule overlaps the \"{$overlap->name}\" {$kind} for some dates — the more recently created rule applies on the overlapping nights.";
    }

    private function forDisplay(PricingRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'is_template' => $rule->is_template,
            'template_key' => $rule->template_key,
            'date_kind' => $rule->date_kind->value,
            'days_of_week' => $rule->days_of_week,
            'start_date' => $rule->start_date?->toDateString(),
            'end_date' => $rule->end_date?->toDateString(),
            'recurring' => $rule->recurring,
            'percentage' => $rule->percentage,
            'ramp_in_days' => $rule->ramp_in_days,
            'ramp_out_days' => $rule->ramp_out_days,
            'active' => $rule->active,
        ];
    }
}
