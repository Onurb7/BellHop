<?php

namespace App\Models;

use App\Enums\PricingRuleDateKind;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $fillable = [
        'name',
        'is_template',
        'template_key',
        'date_kind',
        'days_of_week',
        'start_date',
        'end_date',
        'recurring',
        'percentage',
        'ramp_in_days',
        'ramp_out_days',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
            'date_kind' => PricingRuleDateKind::class,
            'days_of_week' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'recurring' => 'boolean',
            'percentage' => 'integer',
            'ramp_in_days' => 'integer',
            'ramp_out_days' => 'integer',
            'active' => 'boolean',
        ];
    }
}
