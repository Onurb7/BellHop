<?php

namespace App\Models;

use App\Enums\ServicePricingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingService extends Model
{
    protected $fillable = [
        'booking_id',
        'service_id',
        'name',
        'pricing_type',
        'unit_price_cents',
        'quantity',
        'nights',
        'line_total_cents',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'pricing_type' => ServicePricingType::class,
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'nights' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
