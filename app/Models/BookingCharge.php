<?php

namespace App\Models;

use App\Enums\BookingChargeCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCharge extends Model
{
    protected $fillable = [
        'booking_id',
        'category',
        'description',
        'amount_cents',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => BookingChargeCategory::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
