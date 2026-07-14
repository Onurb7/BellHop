<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'guest_id',
        'check_in',
        'check_out',
        'status',
        'deposit_cents',
        'last_reminder_sent_at',
        'last_reminder_type',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'status' => BookingStatus::class,
            'last_reminder_sent_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(BookingCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    /**
     * Derived from the charge ledger, not stored — the total can never
     * drift out of sync with the itemized history this way.
     */
    public function totalCents(): int
    {
        return $this->relationLoaded('charges')
            ? $this->charges->sum('amount_cents')
            : (int) $this->charges()->sum('amount_cents');
    }

    public function amountPaidCents(): int
    {
        return $this->relationLoaded('payments')
            ? $this->payments->sum('amount_cents')
            : (int) $this->payments()->sum('amount_cents');
    }

    public function balanceDueCents(): int
    {
        return $this->totalCents() - $this->amountPaidCents();
    }

    public function confirm(): void
    {
        if ($this->status !== BookingStatus::PendingPayment) {
            throw new RuntimeException('Only a pending-payment booking can be confirmed.');
        }

        $this->update(['status' => BookingStatus::Confirmed]);
    }

    public function cancel(): void
    {
        if (! in_array($this->status, [BookingStatus::PendingPayment, BookingStatus::Confirmed], true)) {
            throw new RuntimeException('This booking cannot be cancelled from its current status.');
        }

        $this->update(['status' => BookingStatus::Cancelled]);
    }
}
