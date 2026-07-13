<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ServicePricingType;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoHotelSeeder extends Seeder
{
    /**
     * Seeds a small demo hotel — room types, rooms (across a few floors,
     * with amenities), services, and a spread of bookings around today
     * (past/current/future, with gaps left vacant) so the tape chart has
     * something realistic to render. Skips entirely if rooms already
     * exist, so it's safe to re-run `db:seed`.
     */
    public function run(): void
    {
        if (Room::exists()) {
            return;
        }

        $amenities = collect(['TV', 'Private Bathroom', 'Air Conditioning', 'Minibar', 'Balcony', 'Free WiFi'])
            ->map(fn (string $name) => Amenity::firstOrCreate(['name' => $name]));

        $roomTypes = collect([
            ['name' => 'Classic Room', 'slug' => 'classic-room', 'base_rate_cents' => 12000, 'max_occupancy' => 2],
            ['name' => 'Deluxe Room', 'slug' => 'deluxe-room', 'base_rate_cents' => 18000, 'max_occupancy' => 3],
            ['name' => 'Executive Suite', 'slug' => 'executive-suite', 'base_rate_cents' => 32000, 'max_occupancy' => 4],
        ])->map(fn (array $attributes) => RoomType::create($attributes));

        Service::insert([
            ['name' => 'Parking', 'slug' => 'parking', 'unit_price_cents' => 1500, 'pricing_type' => ServicePricingType::PerNight->value, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Breakfast', 'slug' => 'breakfast', 'unit_price_cents' => 2000, 'pricing_type' => ServicePricingType::PerNight->value, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Late Checkout', 'slug' => 'late-checkout', 'unit_price_cents' => 3000, 'pricing_type' => ServicePricingType::Flat->value, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $rooms = collect();

        foreach (range(1, 3) as $floor) {
            foreach (range(1, 4) as $i) {
                $roomType = $roomTypes->random();

                $room = Room::create([
                    'room_type_id' => $roomType->id,
                    'title' => "{$roomType->name} — Floor {$floor}",
                    'description' => 'A comfortable, well-appointed room in the heart of the hotel.',
                    'number' => "{$floor}0{$i}",
                    'floor' => (string) $floor,
                    'status' => 'active',
                    'is_published' => true,
                ]);

                $room->amenities()->attach($amenities->random(rand(2, 4))->pluck('id'));
                $rooms->push($room);
            }
        }

        foreach ($rooms as $room) {
            $this->seedBookingsForRoom($room);
        }
    }

    private function seedBookingsForRoom(Room $room): void
    {
        // Leave a fifth of rooms entirely vacant, to demonstrate the
        // "nothing booked, no need to clean" case on the tape chart.
        if (fake()->boolean(20)) {
            return;
        }

        $cursor = Carbon::today()->subDays(fake()->numberBetween(3, 10));
        $horizon = Carbon::today()->addDays(21);

        while ($cursor->lt($horizon)) {
            if (fake()->boolean(35)) {
                $cursor = $cursor->addDays(fake()->numberBetween(1, 4));

                continue;
            }

            $checkIn = $cursor->copy();
            $checkOut = $checkIn->copy()->addDays(fake()->numberBetween(1, 5));

            Booking::create([
                'room_id' => $room->id,
                'guest_id' => Guest::factory()->create()->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => match (true) {
                    $checkOut->lte(Carbon::today()) => BookingStatus::CheckedOut,
                    $checkIn->gt(Carbon::today()) => BookingStatus::Confirmed,
                    default => BookingStatus::CheckedIn,
                },
            ]);

            $cursor = $checkOut->copy();
        }
    }
}
