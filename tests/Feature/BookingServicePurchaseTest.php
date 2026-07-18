<?php

use App\Enums\BookingChargeCategory;
use App\Enums\BookingStatus;
use App\Enums\ServicePricingType;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['rates' => ['EUR' => 0.5]]),
    ]);
});

it('creates booking_services and booking_charges rows for services checked at booking time, without touching the deposit', function () {
    $roomType = RoomType::factory()->create(['base_rate_cents' => 10000, 'currency' => 'USD']);
    $room = Room::factory()->create(['room_type_id' => $roomType->id]);
    $breakfast = Service::factory()->create(['pricing_type' => ServicePricingType::PerNight, 'unit_price_cents' => 1500, 'currency' => 'USD']);
    $petFee = Service::factory()->create(['pricing_type' => ServicePricingType::Flat, 'unit_price_cents' => 2000, 'currency' => 'USD']);

    $booking = Booking::factory()->create([
        'room_id' => $room->id,
        'guest_id' => null,
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(15),
        'check_in' => today()->addDays(10),
        'check_out' => today()->addDays(12),
    ]);

    $this->post("/book/{$booking->id}/guest", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.test',
        'phone' => null,
        'address' => null,
        'services' => [$breakfast->id, $petFee->id],
    ])->assertRedirect(route('booking.show', $booking));

    $booking = $booking->fresh();

    // 2 nights * $15.00 = $30.00
    $breakfastCharge = $booking->charges()->where('category', BookingChargeCategory::Service)->where('description', 'like', '%'.$breakfast->name.'%')->first();
    expect($breakfastCharge->amount_cents)->toBe(3000);

    $petFeeCharge = $booking->charges()->where('category', BookingChargeCategory::Service)->where('description', $petFee->name)->first();
    expect($petFeeCharge->amount_cents)->toBe(2000);

    expect($booking->services)->toHaveCount(2);

    // Deposit is 30% of the room charge only ($200.00 * 0.3), unaffected
    // by the $50.00 of services also on this booking.
    expect($booking->deposit_cents)->toBe(6000);
});

it('lets an authenticated guest purchase a service on their own confirmed booking, added to the balance', function () {
    Role::findOrCreate('guest');

    $user = User::factory()->create();
    $guest = Guest::factory()->create(['user_id' => $user->id]);
    $roomType = RoomType::factory()->create(['currency' => 'USD']);
    $room = Room::factory()->create(['room_type_id' => $roomType->id]);
    $service = Service::factory()->create(['pricing_type' => ServicePricingType::PerNight, 'unit_price_cents' => 1000, 'currency' => 'USD']);

    $booking = Booking::factory()->create([
        'room_id' => $room->id,
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
        'check_in' => today()->addDays(5),
        'check_out' => today()->addDays(10),
    ]);

    $this->actingAs($user)
        ->post("/my-reservations/{$booking->id}/services", [
            'service_id' => $service->id,
            'nights' => 2,
        ])
        ->assertRedirect();

    $booking = $booking->fresh();
    expect($booking->services)->toHaveCount(1);
    expect($booking->services->first()->nights)->toBe(2);
    expect($booking->balanceDueCents())->toBe(2000);
});

it('rejects a guest purchasing a service on a booking they do not own', function () {
    Role::findOrCreate('guest');

    $owner = User::factory()->create();
    $guest = Guest::factory()->create(['user_id' => $owner->id]);
    $intruder = User::factory()->create();
    $service = Service::factory()->create(['pricing_type' => ServicePricingType::Flat]);

    $booking = Booking::factory()->create([
        'guest_id' => $guest->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $this->actingAs($intruder)
        ->post("/my-reservations/{$booking->id}/services", ['service_id' => $service->id])
        ->assertForbidden();
});

it('lets staff purchase a service on a booking for a guest at the front desk', function () {
    Role::findOrCreate('staff');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['pricing_type' => ServicePricingType::Flat, 'unit_price_cents' => 500, 'currency' => 'USD']);
    $booking = Booking::factory()->create(['status' => BookingStatus::CheckedIn]);

    $this->actingAs($staff)
        ->post("/reservations/{$booking->id}/services", [
            'service_id' => $service->id,
            'quantity' => 3,
        ])
        ->assertRedirect();

    $booking = $booking->fresh();
    expect($booking->services()->first()->quantity)->toBe(3);
    expect($booking->balanceDueCents())->toBe(1500);
});

it('clamps requested nights to the booking total even if a larger value is sent', function () {
    Role::findOrCreate('staff');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['pricing_type' => ServicePricingType::PerNight, 'unit_price_cents' => 1000, 'currency' => 'USD']);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'check_in' => today(),
        'check_out' => today()->addDays(3),
    ]);

    $this->actingAs($staff)->post("/reservations/{$booking->id}/services", [
        'service_id' => $service->id,
        'nights' => 99,
    ]);

    expect($booking->fresh()->services()->first()->nights)->toBe(3);
});

it('rejects purchasing a service on a booking that is not confirmed or checked in', function () {
    Role::findOrCreate('staff');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['pricing_type' => ServicePricingType::Flat]);
    $booking = Booking::factory()->create(['status' => BookingStatus::CheckedOut]);

    $this->actingAs($staff)
        ->post("/reservations/{$booking->id}/services", ['service_id' => $service->id])
        ->assertStatus(422);
});
