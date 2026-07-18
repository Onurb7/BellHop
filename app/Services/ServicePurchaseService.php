<?php

namespace App\Services;

use App\Enums\BookingChargeCategory;
use App\Enums\ServicePricingType;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class ServicePurchaseService
{
    public function __construct(private ExchangeRateService $exchangeRates) {}

    /**
     * Attaches a service to a booking: converts the service's own-currency
     * price to USD (the ledger is always USD, same as the room charge),
     * snapshots it onto a booking_services row, and writes the matching
     * booking_charges entry that totalCents()/balanceDueCents() already
     * sum live. Used identically for the booking-time checkboxes and the
     * later guest/staff purchase flow — the only difference between those
     * callers is where quantity/nights come from.
     */
    public function purchase(Booking $booking, Service $service, int $quantity, ?int $nights, ?int $addedByUserId): BookingCharge
    {
        $unitPriceUsdCents = $this->exchangeRates->convertCents(
            $service->unit_price_cents,
            $service->currency,
            'USD',
        );

        $lineTotalCents = $service->pricing_type === ServicePricingType::PerNight
            ? $unitPriceUsdCents * $nights * $quantity
            : $unitPriceUsdCents * $quantity;

        return DB::transaction(function () use ($booking, $service, $quantity, $nights, $unitPriceUsdCents, $lineTotalCents, $addedByUserId) {
            $booking->services()->create([
                'service_id' => $service->id,
                'name' => $service->name,
                'pricing_type' => $service->pricing_type,
                'unit_price_cents' => $unitPriceUsdCents,
                'quantity' => $quantity,
                'nights' => $nights,
                'line_total_cents' => $lineTotalCents,
                'added_by' => $addedByUserId,
            ]);

            return $booking->charges()->create([
                'category' => BookingChargeCategory::Service,
                'description' => $service->pricing_type === ServicePricingType::PerNight
                    ? "{$service->name} — {$nights} night(s)"
                    : ($quantity > 1 ? "{$quantity}× {$service->name}" : $service->name),
                'amount_cents' => $lineTotalCents,
            ]);
        });
    }
}
