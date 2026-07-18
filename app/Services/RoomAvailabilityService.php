<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * Shared by the staff walk-in flow (ReservationController) and the
 * public self-service booking flow (Public\BookingController) — both
 * need the exact same exclusion-constraint-aware availability search
 * and locking mechanics, just wrapped in different response shapes.
 */
class RoomAvailabilityService
{
    /**
     * @return array<int, array{room_id: int, room_number: string, floor: string, room_type_id: int, room_type_name: string, max_occupancy: int, nightly_rate_cents: int, total_cents: int, currency: string}>
     */
    public function searchAvailableRooms(Carbon $checkIn, Carbon $checkOut, ?int $guests = null): array
    {
        $nights = $checkIn->diffInDays($checkOut);

        $roomsQuery = Room::with('roomType')
            ->where('status', RoomStatus::Active->value)
            ->orderBy('number');

        if ($guests) {
            $roomsQuery->whereHas('roomType', fn ($query) => $query->where('max_occupancy', '>=', $guests));
        }

        $rooms = [];

        foreach ($roomsQuery->get() as $room) {
            if (! $this->isAvailable($room->id, $checkIn, $checkOut)) {
                continue;
            }

            $rooms[] = [
                'room_id' => $room->id,
                'room_number' => $room->number,
                'floor' => $room->floor,
                'room_type_id' => $room->room_type_id,
                'room_type_name' => $room->roomType->name,
                'max_occupancy' => $room->roomType->max_occupancy,
                'nightly_rate_cents' => $room->roomType->base_rate_cents,
                'total_cents' => $nights * $room->roomType->base_rate_cents,
                'currency' => $room->roomType->currency,
            ];
        }

        return $rooms;
    }

    public function isAvailable(int $roomId, Carbon $checkIn, Carbon $checkOut, ?int $excludingBookingId = null): bool
    {
        return ! Booking::where('room_id', $roomId)
            ->when($excludingBookingId, fn ($query) => $query->where('id', '!=', $excludingBookingId))
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::NoShow->value])
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
    }

    /**
     * Creates a pending_payment hold on a room. The isAvailable() check
     * above is just a friendly pre-check for the common case — the
     * Postgres exclusion constraint on `bookings` is the actual source
     * of truth against a concurrent race, caught here as a 23P01 error.
     *
     * @throws RoomUnavailableException
     */
    public function lock(int $roomId, Carbon $checkIn, Carbon $checkOut, int $holdMinutes = 15): Booking
    {
        if (! $this->isAvailable($roomId, $checkIn, $checkOut)) {
            throw new RoomUnavailableException;
        }

        try {
            return Booking::create([
                'room_id' => $roomId,
                'guest_id' => null,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => BookingStatus::PendingPayment,
                'expires_at' => now()->addMinutes($holdMinutes),
            ]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23P01') {
                throw new RoomUnavailableException;
            }

            throw $exception;
        }
    }

    /**
     * Deletes any draft (a pending_payment booking with no guest attached
     * yet — walk-in or public self-service) whose lock has expired, so
     * the room becomes searchable again without needing a scheduled job.
     */
    public function sweepExpiredDrafts(): void
    {
        Booking::whereNull('guest_id')
            ->where('status', BookingStatus::PendingPayment)
            ->where('expires_at', '<', now())
            ->delete();
    }

    public function isLiveDraft(Booking $booking): bool
    {
        if ($booking->guest_id !== null) {
            return false;
        }

        if ($booking->expires_at !== null && $booking->expires_at->isPast()) {
            $booking->delete();

            return false;
        }

        return true;
    }

    /**
     * Scheduled backstop for pending_payment bookings whose hold has
     * expired — separate from sweepExpiredDrafts() above, which only
     * covers guest-less drafts and only runs lazily on-read. A
     * guest-attached pending_payment booking only ever gets an expires_at
     * from the public self-service flow (Public\BookingController::storeGuest())
     * — the staff walk-in flow deliberately leaves it null, since a human
     * staff member is actively managing that booking and shouldn't have it
     * auto-cancelled out from under a guest standing at the counter.
     */
    public function cancelExpiredHolds(): int
    {
        $count = 0;

        Booking::where('status', BookingStatus::PendingPayment)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (Booking $booking) use (&$count) {
                $booking->guest_id === null ? $booking->delete() : $booking->cancel();
                $count++;
            });

        return $count;
    }
}
