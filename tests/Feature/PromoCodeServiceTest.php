<?php

use App\Enums\ServicePricingType;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Service;
use App\Services\PromoCodeService;
use Illuminate\Validation\ValidationException;

function makePromoCode(array $overrides = []): PromoCode
{
    return PromoCode::create(array_merge([
        'code' => 'TESTCODE',
        'percentage' => 10,
        'active' => true,
    ], $overrides));
}

it('discounts the room charge for an unscoped code', function () {
    $promoCode = makePromoCode(['percentage' => 10]);

    $discount = app(PromoCodeService::class)->discountCents($promoCode, 20000, collect(), 3);

    expect($discount)->toBe(2000);
});

it('discounts only the selected services a code is scoped to, ignoring the rest', function () {
    $breakfast = Service::factory()->create(['unit_price_cents' => 2000, 'currency' => 'USD', 'pricing_type' => ServicePricingType::Flat]);
    $parking = Service::factory()->create(['unit_price_cents' => 1500, 'currency' => 'USD', 'pricing_type' => ServicePricingType::Flat]);

    $promoCode = makePromoCode(['percentage' => 100]);
    $promoCode->services()->attach($breakfast->id);

    $discount = app(PromoCodeService::class)->discountCents(
        $promoCode,
        20000,
        collect([$breakfast, $parking]),
        1,
    );

    // Only breakfast (2000) is scoped — parking is selected but not covered.
    expect($discount)->toBe(2000);
});

it('throws when a scoped code has none of its services selected', function () {
    $breakfast = Service::factory()->create();
    $promoCode = makePromoCode();
    $promoCode->services()->attach($breakfast->id);

    expect(fn () => app(PromoCodeService::class)->resolve($promoCode->code, []))
        ->toThrow(ValidationException::class);
});

it('throws for a nonexistent code', function () {
    expect(fn () => app(PromoCodeService::class)->resolve('NOPE', []))
        ->toThrow(ValidationException::class);
});

it('throws for an inactive code', function () {
    $promoCode = makePromoCode(['active' => false]);

    expect(fn () => app(PromoCodeService::class)->resolve($promoCode->code, []))
        ->toThrow(ValidationException::class);
});

it('throws for an expired code', function () {
    $promoCode = makePromoCode(['expires_at' => now()->subDay()]);

    expect(fn () => app(PromoCodeService::class)->resolve($promoCode->code, []))
        ->toThrow(ValidationException::class);
});

it('throws once a code has reached its max_uses', function () {
    $promoCode = makePromoCode(['max_uses' => 1]);
    $booking = Booking::factory()->create();
    $promoCode->redemptions()->create(['booking_id' => $booking->id, 'discount_cents' => 1000]);

    expect(fn () => app(PromoCodeService::class)->resolve($promoCode->code, []))
        ->toThrow(ValidationException::class);
});

it('resolves a code case-insensitively and trims whitespace', function () {
    makePromoCode(['code' => 'SUMMER10']);

    $promoCode = app(PromoCodeService::class)->resolve(' summer10 ', []);

    expect($promoCode->code)->toBe('SUMMER10');
});

it('redeem() creates a redemption row and a negative discount charge', function () {
    $promoCode = makePromoCode();
    $booking = Booking::factory()->create();

    app(PromoCodeService::class)->redeem($promoCode, $booking, 2500);

    expect($promoCode->redemptions()->count())->toBe(1)
        ->and($promoCode->redemptions()->first()->discount_cents)->toBe(2500)
        ->and($booking->fresh()->charges()->where('category', 'discount')->first()->amount_cents)->toBe(-2500);
});
