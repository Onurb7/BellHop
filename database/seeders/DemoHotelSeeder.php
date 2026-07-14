<?php

namespace Database\Seeders;

use App\Enums\BookingChargeCategory;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingStatus;
use App\Enums\ServicePricingType;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
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
            $this->linkDemoGuestBookings();

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

        $this->linkDemoGuestBookings();
    }

    /**
     * Links the seeded `guest` demo account to a `guests` row and gives
     * it a past/active/pending-payment stay each, so the guest dashboard
     * has real data out of the box. Runs on every seed (not just the
     * first) since it's guarded independently — `Room::exists()` short-
     * circuits the room/type/booking generation above, but this needs to
     * backfill onto an already-seeded database too.
     */
    private function linkDemoGuestBookings(): void
    {
        $account = config('demo.accounts.guest');
        $user = $account['email'] ? User::where('email', $account['email'])->first() : null;

        if (! $user) {
            return;
        }

        $guest = Guest::firstOrCreate(
            ['user_id' => $user->id],
            ['first_name' => 'Guest', 'last_name' => 'Demo', 'email' => $user->email],
        );

        if ($guest->bookings()->exists()) {
            return;
        }

        $rooms = Room::with('roomType')->inRandomOrder()->limit(3)->get();

        if ($rooms->count() < 3) {
            return;
        }

        // Dates chosen well outside seedBookingsForRoom's today±(10..21)
        // window so these never collide with the exclusion constraint on
        // whatever bookings that method already generated for these rooms.
        $this->createGuestBooking($guest, $rooms[0], Carbon::today()->subDays(45), Carbon::today()->subDays(41), BookingStatus::CheckedOut);
        $this->createGuestBooking($guest, $rooms[1], Carbon::today()->addDays(35), Carbon::today()->addDays(38), BookingStatus::Confirmed);
        $this->createGuestBooking($guest, $rooms[2], Carbon::today()->addDays(45), Carbon::today()->addDays(47), BookingStatus::PendingPayment);
    }

    private function createGuestBooking(Guest $guest, Room $room, Carbon $checkIn, Carbon $checkOut, BookingStatus $status): void
    {
        $nights = $checkIn->diffInDays($checkOut);
        $roomChargeCents = $nights * $room->roomType->base_rate_cents;
        $depositCents = (int) round($roomChargeCents * 0.3);

        $booking = Booking::create([
            'room_id' => $room->id,
            'guest_id' => $guest->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => $status,
            'deposit_cents' => $depositCents,
        ]);

        $booking->charges()->create([
            'category' => BookingChargeCategory::Room,
            'description' => "Room charge: {$nights} night(s) at {$room->number}",
            'amount_cents' => $roomChargeCents,
        ]);

        if ($status === BookingStatus::PendingPayment) {
            return;
        }

        $booking->payments()->create([
            'kind' => BookingPaymentKind::Deposit,
            'amount_cents' => $depositCents,
            'verified_at' => $checkIn->copy()->subDays(fake()->numberBetween(1, 14)),
        ]);

        if ($status === BookingStatus::CheckedOut) {
            $booking->payments()->create([
                'kind' => BookingPaymentKind::Balance,
                'amount_cents' => $roomChargeCents - $depositCents,
                'verified_at' => $checkOut->copy(),
            ]);
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
            $nights = $checkIn->diffInDays($checkOut);
            $roomChargeCents = $nights * $room->roomType->base_rate_cents;
            $depositCents = (int) round($roomChargeCents * 0.3);

            $status = match (true) {
                $checkOut->lte(Carbon::today()) => BookingStatus::CheckedOut,
                $checkIn->gt(Carbon::today()) => BookingStatus::Confirmed,
                default => BookingStatus::CheckedIn,
            };

            // Occasionally leave a still-upcoming booking awaiting its
            // deposit, so the Reservations page's "Verify Payment" action
            // has something real to demonstrate out of the box.
            if ($status === BookingStatus::Confirmed && fake()->boolean(17)) {
                $status = BookingStatus::PendingPayment;
            }

            $booking = Booking::create([
                'room_id' => $room->id,
                'guest_id' => Guest::factory()->create()->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => $status,
                'deposit_cents' => $depositCents,
            ]);

            $booking->charges()->create([
                'category' => BookingChargeCategory::Room,
                'description' => "Room charge: {$nights} night(s) at {$room->number}",
                'amount_cents' => $roomChargeCents,
            ]);

            if ($status !== BookingStatus::PendingPayment) {
                $booking->payments()->create([
                    'kind' => BookingPaymentKind::Deposit,
                    'amount_cents' => $depositCents,
                    'verified_at' => $checkIn->copy()->subDays(fake()->numberBetween(1, 14)),
                ]);

                if ($status === BookingStatus::CheckedOut) {
                    $booking->payments()->create([
                        'kind' => BookingPaymentKind::Balance,
                        'amount_cents' => $roomChargeCents - $depositCents,
                        'verified_at' => $checkOut->copy(),
                    ]);
                }
            }

            $cursor = $checkOut->copy();
        }
    }
}
