<?php

namespace Database\Seeders;

use App\Enums\ServicePricingType;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Services\DemoActivitySeeder;
use Illuminate\Database\Seeder;

class DemoHotelSeeder extends Seeder
{
    /**
     * Room-type slug => image shot list, matched against
     * database/seeders/assets/rooms/{slug}/{shot}.webp — see that
     * directory's README.md for the full manifest. Missing files are
     * skipped silently (attachMasterImage()), so this works today with
     * zero images present.
     */
    private const ROOM_IMAGE_SHOTS = [
        'classic-room' => ['bedroom', 'bathroom'],
        'deluxe-room' => ['bedroom', 'bathroom', 'seating-area'],
        'executive-suite' => ['bedroom', 'bathroom', 'living-room', 'balcony-view'],
    ];

    /**
     * Seeds a small demo hotel — room types, rooms (across a few floors,
     * with amenities and stock photos), services, and delegates guest/
     * booking generation to DemoActivitySeeder. Skips entirely if rooms
     * already exist, so it's safe to re-run `db:seed`.
     */
    public function run(): void
    {
        $activity = app(DemoActivitySeeder::class);

        if (Room::exists()) {
            $activity->linkDemoGuestBookings();

            return;
        }

        $amenities = collect(['TV', 'Private Bathroom', 'Air Conditioning', 'Minibar', 'Balcony', 'Free WiFi'])
            ->map(fn (string $name) => Amenity::firstOrCreate(['name' => $name]));

        $roomTypes = collect([
            ['name' => 'Classic Room', 'slug' => 'classic-room', 'base_rate_cents' => 12000, 'max_occupancy' => 2],
            ['name' => 'Deluxe Room', 'slug' => 'deluxe-room', 'base_rate_cents' => 18000, 'max_occupancy' => 3],
            ['name' => 'Executive Suite', 'slug' => 'executive-suite', 'base_rate_cents' => 32000, 'max_occupancy' => 4],
        ])->map(fn (array $attributes) => RoomType::create($attributes));

        collect([
            ['name' => 'Parking', 'slug' => 'parking', 'unit_price_cents' => 1500, 'pricing_type' => ServicePricingType::PerNight],
            ['name' => 'Breakfast', 'slug' => 'breakfast', 'unit_price_cents' => 2000, 'pricing_type' => ServicePricingType::PerNight],
            ['name' => 'Late Checkout', 'slug' => 'late-checkout', 'unit_price_cents' => 3000, 'pricing_type' => ServicePricingType::Flat],
            ['name' => 'Airport Shuttle', 'slug' => 'airport-shuttle', 'unit_price_cents' => 4500, 'pricing_type' => ServicePricingType::Flat],
            ['name' => 'Spa Treatment', 'slug' => 'spa-treatment', 'unit_price_cents' => 8000, 'pricing_type' => ServicePricingType::Flat],
            ['name' => 'Minibar Restock', 'slug' => 'minibar-restock', 'unit_price_cents' => 2500, 'pricing_type' => ServicePricingType::Flat],
            ['name' => 'In-Room Dining', 'slug' => 'in-room-dining', 'unit_price_cents' => 3500, 'pricing_type' => ServicePricingType::Flat],
            ['name' => 'Pet Fee', 'slug' => 'pet-fee', 'unit_price_cents' => 2000, 'pricing_type' => ServicePricingType::Flat],
            ['name' => 'Bike Rental', 'slug' => 'bike-rental', 'unit_price_cents' => 1800, 'pricing_type' => ServicePricingType::PerNight],
        ])->each(function (array $attributes) {
            $service = Service::create(['active' => true, ...$attributes]);
            $this->attachMasterImage($service, database_path("seeders/assets/services/{$service->slug}.webp"));
        });

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
                $this->attachRoomImages($room, $roomType->slug);
            }
        }

        $activity->reseedGuestsAndBookings();
    }

    private function attachRoomImages(Room $room, string $roomTypeSlug): void
    {
        foreach (self::ROOM_IMAGE_SHOTS[$roomTypeSlug] ?? [] as $shot) {
            $this->attachMasterImage($room, database_path("seeders/assets/rooms/{$roomTypeSlug}/{$shot}.webp"));
        }
    }

    private function attachMasterImage(Room|Service $model, string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        $model->addMedia($path)->preservingOriginal()->toMediaCollection('images');
    }
}
