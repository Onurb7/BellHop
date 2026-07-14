<?php

namespace App\Models;

use App\Enums\BookingPaymentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    protected $fillable = [
        'booking_id',
        'kind',
        'amount_cents',
        'verified_by',
        'verified_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'kind' => BookingPaymentKind::class,
            'verified_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
