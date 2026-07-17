<?php

namespace App\Models;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Booking extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'room_id',
        'guest_id',
        'check_in',
        'check_out',
        'status',
        'deposit_cents',
        'last_reminder_sent_at',
        'last_reminder_type',
        'expires_at',
        'invoice_number',
        'invoice_generated_at',
        'stripe_payment_method_id',
        'stripe_customer_id',
        'balance_due_at',
        'balance_collection_failed_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'status' => BookingStatus::class,
            'last_reminder_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'invoice_generated_at' => 'datetime',
            'balance_due_at' => 'datetime',
            'balance_collection_failed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('invoice')->singleFile();
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

    public function hasInvoice(): bool
    {
        return $this->invoice_generated_at !== null;
    }

    /**
     * What payment is next due — deposit, then balance, then any
     * additional amount still owed — shared by the staff manual-verify
     * flow and the guest Stripe payment flow so the "which kind, how
     * much" logic exists in exactly one place.
     *
     * @return array{kind: BookingPaymentKind, amount_cents: int}
     */
    public function nextPaymentKind(): array
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        $hasDeposit = $payments->contains(fn (BookingPayment $payment) => $payment->kind === BookingPaymentKind::Deposit);
        $hasBalance = $payments->contains(fn (BookingPayment $payment) => $payment->kind === BookingPaymentKind::Balance);

        $kind = match (true) {
            ! $hasDeposit => BookingPaymentKind::Deposit,
            ! $hasBalance => BookingPaymentKind::Balance,
            default => BookingPaymentKind::Additional,
        };

        $amountCents = $kind === BookingPaymentKind::Deposit
            ? ($this->deposit_cents ?? $this->totalCents())
            : $this->balanceDueCents();

        return ['kind' => $kind, 'amount_cents' => $amountCents];
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

    public function checkIn(): void
    {
        if ($this->status !== BookingStatus::Confirmed) {
            throw new RuntimeException('Only a confirmed booking can be checked in.');
        }

        $this->update(['status' => BookingStatus::CheckedIn]);
    }

    public function checkOut(): void
    {
        if ($this->status !== BookingStatus::CheckedIn) {
            throw new RuntimeException('Only a checked-in booking can be checked out.');
        }

        $this->update(['status' => BookingStatus::CheckedOut]);
    }

    /**
     * Sweep-only — reachable exclusively from the nightly no-show command,
     * never a manual staff action (matches the domain plan exactly).
     */
    public function markNoShow(): void
    {
        if ($this->status !== BookingStatus::Confirmed) {
            throw new RuntimeException('Only a confirmed booking can be marked a no-show.');
        }

        $this->update(['status' => BookingStatus::NoShow]);
    }
}
