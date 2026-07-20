<?php

namespace App\Services;

use App\Enums\BookingChargeCategory;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoActivitySeeder
{
    public function __construct(private ExchangeRateService $exchangeRates, private SeasonalPricingService $pricing)
    {
    }

    /**
     * Wipes ALL guests/bookings (their charges/payments cascade via FK)
     * and regenerates a fresh, today-anchored spread — rooms/room types/
     * amenities/services are never touched here, only guests/bookings.
     * Bookings are deleted before guests: bookings.guest_id and
     * bookings.room_id are both restrictOnDelete(), so a guest can't be
     * deleted while any booking still references it. Delete-then-insert
     * inside one transaction is safe against the bookings_no_overlapping_
     * stays exclusion constraint, since it's checked per-statement and the
     * old rows are already gone by the time new ones insert.
     *
     * Called both by the scheduled demo:reseed-activity command (monthly)
     * and once, harmlessly, by DemoHotelSeeder's first-ever run
     * (nothing exists yet to delete). Queries Room::all() fresh each call,
     * so any room added later via /admin/rooms is automatically included
     * in every subsequent reseed — no code changes needed.
     */
    public function reseedGuestsAndBookings(): void
    {
        DB::transaction(function () {
            Booking::query()->delete();
            Guest::query()->delete();

            Room::with('roomType')->get()->each(fn (Room $room) => $this->seedBookingsForRoom($room));

            $this->linkDemoGuestBookings();
        });
    }

    /**
     * Links the seeded `guest` demo account to a `guests` row and gives
     * it a past/active/pending-payment stay each, so the guest dashboard
     * has real data out of the box. Runs on every seed (not just the
     * first) since it's guarded independently.
     */
    public function linkDemoGuestBookings(): void
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

        // Dates chosen well outside seedBookingsForRoom's today-2mo..+1mo
        // window so these never collide with the exclusion constraint on
        // whatever bookings that method already generated for these rooms.
        $this->createGuestBooking($guest, $rooms[0], Carbon::today()->subDays(75), Carbon::today()->subDays(71), BookingStatus::CheckedOut);
        $this->createGuestBooking($guest, $rooms[1], Carbon::today()->addDays(40), Carbon::today()->addDays(43), BookingStatus::Confirmed);
        $this->createGuestBooking($guest, $rooms[2], Carbon::today()->addDays(50), Carbon::today()->addDays(52), BookingStatus::PendingPayment);
    }

    private function createGuestBooking(Guest $guest, Room $room, Carbon $checkIn, Carbon $checkOut, BookingStatus $status): void
    {
        $nights = $checkIn->diffInDays($checkOut);
        // Ledger amounts are always USD — same conversion the real booking
        // flows apply at storeGuest() time (see Public\BookingController
        // and ReservationController), now on top of the same seasonal
        // per-night adjustment those flows apply too.
        $seasonalTotalCents = $this->pricing->totalRoomChargeCents($room->roomType, $checkIn, $checkOut);
        $roomChargeCents = $this->exchangeRates->convertCents($seasonalTotalCents, $room->roomType->currency, 'USD');
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

    /**
     * Spread runs today-2 months to today+1 month (wide enough to give the
     * dashboard's capacity trend charts real history to render, not a
     * mostly-flat line) — already relative to Carbon::today(), so it
     * naturally rolls forward every time this runs.
     */
    private function seedBookingsForRoom(Room $room): void
    {
        // Leave a fifth of rooms entirely vacant, to demonstrate the
        // "nothing booked, no need to clean" case on the tape chart.
        if (fake()->boolean(20)) {
            return;
        }

        $cursor = Carbon::today()->subMonths(2);
        $horizon = Carbon::today()->addMonth();

        while ($cursor->lt($horizon)) {
            if (fake()->boolean(35)) {
                $cursor = $cursor->addDays(fake()->numberBetween(1, 4));

                continue;
            }

            $checkIn = $cursor->copy();
            $checkOut = $checkIn->copy()->addDays(fake()->numberBetween(1, 5));
            $nights = $checkIn->diffInDays($checkOut);
            $seasonalTotalCents = $this->pricing->totalRoomChargeCents($room->roomType, $checkIn, $checkOut);
            $roomChargeCents = $this->exchangeRates->convertCents($seasonalTotalCents, $room->roomType->currency, 'USD');
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
