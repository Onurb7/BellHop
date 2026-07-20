<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingRuleDateKind;
use App\Models\PricingRule;
use Illuminate\Foundation\Http\FormRequest;

class PricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rule = $this->route('pricingRule');
        $dateKind = $rule?->date_kind ?? PricingRuleDateKind::DateRange;
        $isTemplate = $rule?->is_template ?? false;

        return [
            'name' => $isTemplate ? ['sometimes'] : ['required', 'string', 'max:255'],
            'start_date' => [$dateKind === PricingRuleDateKind::DateRange ? 'required' : 'sometimes', 'date'],
            'end_date' => [$dateKind === PricingRuleDateKind::DateRange ? 'required' : 'sometimes', 'date', 'after_or_equal:start_date'],
            'recurring' => ['boolean'],
            'days_of_week' => [$dateKind === PricingRuleDateKind::DayOfWeek ? 'required' : 'sometimes', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            // -90 floors a room above $0; 500 is a generous but sane cap
            // rather than leaving it unbounded.
            'percentage' => ['required', 'integer', 'between:-90,500'],
            'ramp_in_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'ramp_out_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'active' => ['boolean'],
        ];
    }

    /**
     * A template's identity (name/date_kind/template_key) is never
     * writable through this request — only set once, by the seeder.
     * Passing the target rule (null for a brand-new manual rule) is what
     * decides whether identity fields are included at all.
     */
    public function validatedForModel(?PricingRule $rule = null): array
    {
        $data = $this->validated();
        $isTemplate = $rule?->is_template ?? false;
        $dateKind = $rule?->date_kind ?? PricingRuleDateKind::DateRange;

        $payload = [
            'percentage' => $data['percentage'],
            'ramp_in_days' => $data['ramp_in_days'] ?? 0,
            'ramp_out_days' => $data['ramp_out_days'] ?? 0,
            'active' => $data['active'] ?? false,
        ];

        if (! $isTemplate) {
            $payload['name'] = $data['name'];
            $payload['date_kind'] = PricingRuleDateKind::DateRange;
            $payload['is_template'] = false;
        }

        if ($dateKind === PricingRuleDateKind::DateRange) {
            $payload['start_date'] = $data['start_date'];
            $payload['end_date'] = $data['end_date'];
            $payload['recurring'] = $data['recurring'] ?? false;
        } else {
            $payload['days_of_week'] = $data['days_of_week'] ?? [];
        }

        return $payload;
    }
}
