<?php

namespace App\Services;

use App\Enums\BookingChargeCategory;
use App\Enums\ServicePricingType;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromoCodeService
{
    public function __construct(private ServicePurchaseService $servicePurchase) {}

    /**
     * Looks up and validates a code against what's currently selected —
     * shared by the read-only Apply-button preview and the real
     * storeGuest() redemption, so the two can never disagree about
     * whether a code is usable.
     *
     * @param  array<int>  $selectedServiceIds
     */
    public function resolve(string $code, array $selectedServiceIds): PromoCode
    {
        $promoCode = PromoCode::where('code', strtoupper(trim($code)))
            ->with('services')
            ->first();

        if (! $promoCode || ! $promoCode->active) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code is invalid.']);
        }

        if ($promoCode->expires_at !== null && $promoCode->expires_at->isPast()) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code has expired.']);
        }

        if ($promoCode->max_uses !== null && $promoCode->redemptions()->count() >= $promoCode->max_uses) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code has reached its usage limit.']);
        }

        $scopedServiceIds = $promoCode->services->pluck('id');

        if ($scopedServiceIds->isNotEmpty() && $scopedServiceIds->intersect($selectedServiceIds)->isEmpty()) {
            $names = $promoCode->services->pluck('name')->join(', ');
            throw ValidationException::withMessages(['promo_code' => "This code only applies to: {$names}. Select one to use it."]);
        }

        return $promoCode;
    }

    /**
     * Unscoped (no services attached) discounts the room charge itself;
     * scoped discounts only the selected services the code actually
     * covers — a code scoped to Breakfast never touches Parking's charge
     * even if both were selected.
     *
     * @param  Collection<int, Service>  $selectedServices
     */
    public function discountCents(PromoCode $promoCode, int $roomChargeCents, Collection $selectedServices, int $nights): int
    {
        if ($promoCode->services->isEmpty()) {
            return (int) round($roomChargeCents * $promoCode->percentage / 100);
        }

        $scopedServiceIds = $promoCode->services->pluck('id');

        $applicableCents = $selectedServices
            ->filter(fn (Service $service) => $scopedServiceIds->contains($service->id))
            ->sum(fn (Service $service) => $this->servicePurchase->lineTotalCents(
                $service,
                1,
                $service->pricing_type === ServicePricingType::PerNight ? $nights : null,
            ));

        return (int) round($applicableCents * $promoCode->percentage / 100);
    }

    /**
     * The only place a redemption is ever actually recorded — the
     * Apply-button preview never calls this, only a real booking
     * submission does, so previewing a code can't burn its max_uses.
     */
    public function redeem(PromoCode $promoCode, Booking $booking, int $discountCents): void
    {
        DB::transaction(function () use ($promoCode, $booking, $discountCents) {
            $promoCode->redemptions()->create([
                'booking_id' => $booking->id,
                'discount_cents' => $discountCents,
            ]);

            $booking->charges()->create([
                'category' => BookingChargeCategory::Discount,
                'description' => "Promo code {$promoCode->code}",
                'amount_cents' => -$discountCents,
            ]);
        });
    }
}
