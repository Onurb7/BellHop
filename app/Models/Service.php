<?php

namespace App\Models;

use App\Enums\ServicePricingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'unit_price_cents',
        'pricing_type',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'pricing_type' => ServicePricingType::class,
            'active' => 'boolean',
        ];
    }
}
