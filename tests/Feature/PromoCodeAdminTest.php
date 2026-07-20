<?php

use App\Models\PromoCode;
use App\Models\Service;
use App\Models\User;
use Spatie\Permission\Models\Role;

function actingAsPromoAdmin(): User
{
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function actingAsPromoStaff(): User
{
    Role::findOrCreate('staff');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    return $staff;
}

it('blocks staff from every /admin/promo-codes route', function () {
    $staff = actingAsPromoStaff();
    $promoCode = PromoCode::create(['code' => 'BLOCKED', 'percentage' => 10]);

    $this->actingAs($staff)->get('/admin/promo-codes')->assertForbidden();
    $this->actingAs($staff)->get('/admin/promo-codes/create')->assertForbidden();
    $this->actingAs($staff)->get("/admin/promo-codes/{$promoCode->id}/edit")->assertForbidden();
    $this->actingAs($staff)->post('/admin/promo-codes', [])->assertForbidden();
    $this->actingAs($staff)->delete("/admin/promo-codes/{$promoCode->id}")->assertForbidden();
});

it('lets an admin create a promo code', function () {
    $admin = actingAsPromoAdmin();

    $this->actingAs($admin)->post('/admin/promo-codes', [
        'code' => 'newcode',
        'percentage' => 15,
        'active' => true,
    ])->assertRedirect('/admin/promo-codes');

    $promoCode = PromoCode::first();
    expect($promoCode->code)->toBe('NEWCODE') // normalized uppercase
        ->and($promoCode->percentage)->toBe(15);
});

it('rejects a duplicate code', function () {
    $admin = actingAsPromoAdmin();
    PromoCode::create(['code' => 'EXISTING', 'percentage' => 10]);

    $this->actingAs($admin)->post('/admin/promo-codes', [
        'code' => 'EXISTING',
        'percentage' => 20,
    ])->assertSessionHasErrors('code');
});

it('syncs scoped services on update', function () {
    $admin = actingAsPromoAdmin();
    $promoCode = PromoCode::create(['code' => 'SCOPED', 'percentage' => 10]);
    $breakfast = Service::factory()->create();
    $parking = Service::factory()->create();

    $this->actingAs($admin)->put("/admin/promo-codes/{$promoCode->id}", [
        'code' => 'SCOPED',
        'percentage' => 10,
        'service_ids' => [$breakfast->id, $parking->id],
    ])->assertRedirect('/admin/promo-codes');

    expect($promoCode->fresh()->services->pluck('id')->sort()->values()->all())
        ->toBe([$breakfast->id, $parking->id]);
});

it('refuses to delete a code that already has redemptions', function () {
    $admin = actingAsPromoAdmin();
    $promoCode = PromoCode::create(['code' => 'USED', 'percentage' => 10]);
    $booking = \App\Models\Booking::factory()->create();
    $promoCode->redemptions()->create(['booking_id' => $booking->id, 'discount_cents' => 1000]);

    $this->actingAs($admin)->delete("/admin/promo-codes/{$promoCode->id}")->assertStatus(422);

    expect(PromoCode::find($promoCode->id))->not->toBeNull();
});

it('lets an admin deactivate a used code instead of deleting it', function () {
    $admin = actingAsPromoAdmin();
    $promoCode = PromoCode::create(['code' => 'USED2', 'percentage' => 10, 'active' => true]);
    $booking = \App\Models\Booking::factory()->create();
    $promoCode->redemptions()->create(['booking_id' => $booking->id, 'discount_cents' => 1000]);

    $this->actingAs($admin)->put("/admin/promo-codes/{$promoCode->id}", [
        'code' => 'USED2',
        'percentage' => 10,
        'active' => false,
    ])->assertRedirect('/admin/promo-codes');

    expect($promoCode->fresh()->active)->toBeFalse();
});
