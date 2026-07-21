<?php

use App\Enums\ServicePricingType;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use Spatie\Permission\Models\Role;

function lockRoom(int $roomTypeCents = 10000, int $daysOut = 10, int $nights = 1): Booking
{
    $roomType = RoomType::factory()->create(['base_rate_cents' => $roomTypeCents, 'currency' => 'USD']);
    $room = Room::factory()->create(['room_type_id' => $roomType->id]);

    return app(RoomAvailabilityService::class)->lock(
        $room->id,
        today()->addDays($daysOut),
        today()->addDays($daysOut + $nights),
    );
}

function staffUser(): User
{
    Role::findOrCreate('staff');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    return $staff;
}

it('applies an unscoped code on the public booking flow, discounting the room and adjusting the deposit', function () {
    $booking = lockRoom(10000, 10);
    PromoCode::create(['code' => 'SAVE10', 'percentage' => 10, 'active' => true]);

    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.promo@example.test',
        'promo_code' => 'save10',
    ])->assertSessionHasNoErrors();

    $fresh = $booking->fresh();
    $roomCharge = $fresh->charges()->where('category', 'room')->first();
    $discountCharge = $fresh->charges()->where('category', 'discount')->first();

    expect($roomCharge->amount_cents)->toBe(10000) // Room charge stays undiscounted
        ->and($discountCharge->amount_cents)->toBe(-1000)
        ->and($fresh->totalCents())->toBe(9000)
        ->and($fresh->deposit_cents)->toBe(2700) // 30% of (10000 - 1000)
        ->and($fresh->isDepositPlan())->toBeTrue() // Room charge itself is untouched by the discount
        ->and(PromoCode::where('code', 'SAVE10')->first()->redemptions()->count())->toBe(1);
});

it('rejects an invalid promo code on the public booking flow without touching the booking', function () {
    $booking = lockRoom(10000, 10);

    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.badcode@example.test',
        'promo_code' => 'NOPE',
    ])->assertSessionHasErrors('promo_code');

    $fresh = $booking->fresh();

    expect($fresh->guest_id)->toBeNull()
        ->and($fresh->charges()->count())->toBe(0);
});

it('applies a service-scoped code on the staff walk-in flow, discounting only that service', function () {
    $staff = staffUser();
    $booking = lockRoom(10000, 10);
    $breakfast = Service::factory()->create(['name' => 'Breakfast', 'unit_price_cents' => 4000, 'currency' => 'USD', 'pricing_type' => ServicePricingType::Flat, 'active' => true]);
    $promoCode = PromoCode::create(['code' => 'FREEBREAKFAST', 'percentage' => 100, 'active' => true]);
    $promoCode->services()->attach($breakfast->id);

    $this->actingAs($staff)->post("/reservations/new/{$booking->id}/guest", [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'john.scoped@example.test',
        'services' => [$breakfast->id],
        'promo_code' => 'FREEBREAKFAST',
    ])->assertSessionHasNoErrors();

    $fresh = $booking->fresh();

    expect($fresh->charges()->where('category', 'room')->first()->amount_cents)->toBe(10000)
        ->and($fresh->charges()->where('category', 'service')->first()->amount_cents)->toBe(4000)
        ->and($fresh->charges()->where('category', 'discount')->first()->amount_cents)->toBe(-4000)
        ->and($fresh->deposit_cents)->toBe(3000) // 30% of the room charge, unaffected by a service-scoped discount
        ->and($fresh->totalCents())->toBe(10000);
});

it('rejects a scoped code when its required service was not selected', function () {
    $staff = staffUser();
    $booking = lockRoom(10000, 10);
    $breakfast = Service::factory()->create(['name' => 'Breakfast', 'active' => true]);
    $promoCode = PromoCode::create(['code' => 'FREEBREAKFAST', 'percentage' => 100, 'active' => true]);
    $promoCode->services()->attach($breakfast->id);

    $this->actingAs($staff)->post("/reservations/new/{$booking->id}/guest", [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'john.noservice@example.test',
        'promo_code' => 'FREEBREAKFAST',
    ])->assertSessionHasErrors('promo_code');
});

it('rejects a code once it has reached its max_uses', function () {
    $staff = staffUser();
    $promoCode = PromoCode::create(['code' => 'ONEUSE', 'percentage' => 10, 'max_uses' => 1, 'active' => true]);

    $firstBooking = lockRoom(10000, 10);
    $this->actingAs($staff)->post("/reservations/new/{$firstBooking->id}/guest", [
        'first_name' => 'A', 'last_name' => 'One', 'email' => 'a.one@example.test', 'promo_code' => 'ONEUSE',
    ])->assertSessionHasNoErrors();

    $secondBooking = lockRoom(10000, 11);
    $this->actingAs($staff)->post("/reservations/new/{$secondBooking->id}/guest", [
        'first_name' => 'B', 'last_name' => 'Two', 'email' => 'b.two@example.test', 'promo_code' => 'ONEUSE',
    ])->assertSessionHasErrors('promo_code');

    expect($promoCode->redemptions()->count())->toBe(1);
});

it('lets the public preview endpoint check a code without redeeming it', function () {
    $booking = lockRoom(10000, 10);
    PromoCode::create(['code' => 'SAVE10', 'description' => 'Ten percent off', 'percentage' => 10, 'active' => true]);

    $response = $this->postJson("/book/{$booking->id}/promo-code/preview", ['code' => 'save10', 'services' => []]);

    $response->assertOk()->assertJson([
        'valid' => true,
        'code' => 'SAVE10',
        'description' => 'Ten percent off',
        'discount_cents' => 1000,
    ]);
    expect(PromoCode::where('code', 'SAVE10')->first()->redemptions()->count())->toBe(0);
});

it('lets the public preview endpoint reject an invalid code with a message', function () {
    $booking = lockRoom(10000, 10);

    $this->postJson("/book/{$booking->id}/promo-code/preview", ['code' => 'NOPE', 'services' => []])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);
});

it('lets the staff preview endpoint check a code without redeeming it', function () {
    $staff = staffUser();
    $booking = lockRoom(10000, 10);
    PromoCode::create(['code' => 'SAVE10', 'percentage' => 10, 'active' => true]);

    $this->actingAs($staff)
        ->postJson("/reservations/new/{$booking->id}/promo-code/preview", ['code' => 'SAVE10', 'services' => []])
        ->assertOk()
        ->assertJson(['valid' => true, 'discount_cents' => 1000]);

    expect(PromoCode::where('code', 'SAVE10')->first()->redemptions()->count())->toBe(0);
});
