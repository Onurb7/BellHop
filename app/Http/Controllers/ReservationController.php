<?php

namespace App\Http\Controllers;

use App\Enums\BookingChargeCategory;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Mail\PaymentReminderMail;
use App\Mail\ReservationReminderMail;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->value() ?: null;
        $search = $request->string('search')->value() ?: null;

        $bookings = Booking::with(['room.roomType', 'guest'])
            ->whereNotNull('guest_id')
            ->withSum('charges as total_cents', 'amount_cents')
            ->withSum('payments as amount_paid_cents', 'amount_cents')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereHas('guest', function ($guestQuery) use ($search) {
                    $guestQuery->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$search}%"]);
                })->orWhereHas('room', function ($roomQuery) use ($search) {
                    $roomQuery->where('number', 'ilike', "%{$search}%")
                        ->orWhereHas('roomType', fn ($typeQuery) => $typeQuery->where('name', 'ilike', "%{$search}%"));
                });
            }))
            ->orderByDesc('check_in')
            ->paginate(15)
            ->withQueryString();

        $bookings->through(function (Booking $booking) {
            $totalCents = (int) ($booking->total_cents ?? 0);
            $paidCents = (int) ($booking->amount_paid_cents ?? 0);

            return [
                'id' => $booking->id,
                'guest_name' => $booking->guest->name,
                'room_number' => $booking->room->number,
                'room_type' => $booking->room->roomType->name,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'status' => $booking->status->value,
                'total_cents' => $totalCents,
                'amount_paid_cents' => $paidCents,
                'balance_due_cents' => $totalCents - $paidCents,
            ];
        });

        return Inertia::render('Reservations/Index', [
            'status' => $status,
            'search' => $search,
            'statuses' => array_map(fn (BookingStatus $case) => $case->value, BookingStatus::cases()),
            'bookings' => $bookings,
        ]);
    }

    public function newSearch(Request $request): Response
    {
        $this->sweepExpiredDrafts();

        $checkIn = $request->filled('check_in') ? Carbon::parse($request->string('check_in')->value()) : null;
        $checkOut = $request->filled('check_out') ? Carbon::parse($request->string('check_out')->value()) : null;
        $guests = $request->filled('guests') ? $request->integer('guests') : null;

        $rooms = [];

        if ($checkIn && $checkOut && $checkOut->gt($checkIn)) {
            $nights = $checkIn->diffInDays($checkOut);

            $roomsQuery = Room::with('roomType')
                ->where('status', RoomStatus::Active->value)
                ->orderBy('number');

            if ($guests) {
                $roomsQuery->whereHas('roomType', fn ($query) => $query->where('max_occupancy', '>=', $guests));
            }

            foreach ($roomsQuery->get() as $room) {
                if (! $this->roomIsAvailable($room->id, $checkIn, $checkOut)) {
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
                ];
            }
        }

        return Inertia::render('Reservations/New/Search', [
            'check_in' => $checkIn?->toDateString(),
            'check_out' => $checkOut?->toDateString(),
            'guests' => $guests,
            'nights' => $checkIn && $checkOut ? $checkIn->diffInDays($checkOut) : null,
            'rooms' => $rooms,
        ]);
    }

    public function lock(Request $request): RedirectResponse
    {
        $this->sweepExpiredDrafts();

        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);

        if (! $this->roomIsAvailable((int) $data['room_id'], $checkIn, $checkOut)) {
            return back()->withErrors(['room_id' => 'That room was just taken — please pick another.']);
        }

        try {
            $booking = Booking::create([
                'room_id' => $data['room_id'],
                'guest_id' => null,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => BookingStatus::PendingPayment,
                'expires_at' => now()->addMinutes(15),
            ]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23P01') {
                return back()->withErrors(['room_id' => 'That room was just taken — please pick another.']);
            }

            throw $exception;
        }

        return redirect()->route('reservations.new.guest', $booking);
    }

    public function newGuestForm(Booking $booking): RedirectResponse|Response
    {
        if (! $this->isLiveDraft($booking)) {
            return redirect()->route('reservations.new.search')
                ->with('error', 'That hold has expired or was already completed — please search again.');
        }

        $booking->loadMissing('room.roomType');
        $nights = $booking->check_in->diffInDays($booking->check_out);
        $roomChargeCents = $nights * $booking->room->roomType->base_rate_cents;

        return Inertia::render('Reservations/New/Guest', [
            'booking' => [
                'id' => $booking->id,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'expires_at' => $booking->expires_at->toIso8601String(),
                'nights' => $nights,
                'total_cents' => $roomChargeCents,
                'deposit_cents' => (int) round($roomChargeCents * 0.3),
                'room' => [
                    'number' => $booking->room->number,
                    'room_type' => $booking->room->roomType->name,
                ],
            ],
        ]);
    }

    public function storeGuest(Booking $booking, Request $request): RedirectResponse
    {
        if (! $this->isLiveDraft($booking)) {
            return redirect()->route('reservations.new.search')
                ->with('error', 'That hold has expired or was already completed — please search again.');
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $guest = Guest::firstOrCreate(
            ['email' => $data['email']],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ],
        );

        $booking->loadMissing('room.roomType');
        $nights = $booking->check_in->diffInDays($booking->check_out);
        $roomChargeCents = $nights * $booking->room->roomType->base_rate_cents;

        DB::transaction(function () use ($booking, $guest, $roomChargeCents, $nights) {
            $booking->update([
                'guest_id' => $guest->id,
                'expires_at' => null,
                'deposit_cents' => (int) round($roomChargeCents * 0.3),
            ]);

            $booking->charges()->create([
                'category' => BookingChargeCategory::Room,
                'description' => "Room charge: {$nights} night(s) at {$booking->room->number}",
                'amount_cents' => $roomChargeCents,
            ]);
        });

        return redirect()->route('reservations.show', $booking)->with('success', 'Reservation created.');
    }

    public function abandon(Booking $booking): RedirectResponse
    {
        if ($booking->guest_id === null) {
            $booking->delete();
        }

        return redirect()->route('reservations.new.search');
    }

    public function show(Booking $booking): Response
    {
        $booking->load([
            'room.roomType',
            'guest',
            'charges' => fn ($query) => $query->orderBy('created_at'),
            'payments' => fn ($query) => $query->orderBy('created_at'),
        ]);

        return Inertia::render('Reservations/Show', [
            'booking' => [
                'id' => $booking->id,
                'status' => $booking->status->value,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'deposit_cents' => $booking->deposit_cents,
                'last_reminder_sent_at' => $booking->last_reminder_sent_at?->diffForHumans(),
                'last_reminder_type' => $booking->last_reminder_type,
                'total_cents' => $booking->totalCents(),
                'amount_paid_cents' => $booking->amountPaidCents(),
                'balance_due_cents' => $booking->balanceDueCents(),
                'guest' => [
                    'id' => $booking->guest->id,
                    'name' => $booking->guest->name,
                    'email' => $booking->guest->email,
                    'phone' => $booking->guest->phone,
                ],
                'room' => [
                    'id' => $booking->room->id,
                    'number' => $booking->room->number,
                    'floor' => $booking->room->floor,
                    'room_type_id' => $booking->room->room_type_id,
                    'room_type' => $booking->room->roomType->name,
                ],
                'charges' => $booking->charges->map(fn ($charge) => [
                    'id' => $charge->id,
                    'category' => $charge->category->value,
                    'description' => $charge->description,
                    'amount_cents' => $charge->amount_cents,
                    'created_at' => $charge->created_at->toDateTimeString(),
                ]),
                'payments' => $booking->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'kind' => $payment->kind->value,
                    'amount_cents' => $payment->amount_cents,
                    'verified_at' => $payment->verified_at->toDateTimeString(),
                ]),
            ],
        ]);
    }

    public function verifyPayment(Booking $booking): RedirectResponse
    {
        $booking->loadMissing('payments');

        $hasDeposit = $booking->payments->contains(fn ($payment) => $payment->kind === BookingPaymentKind::Deposit);
        $hasBalance = $booking->payments->contains(fn ($payment) => $payment->kind === BookingPaymentKind::Balance);

        $kind = match (true) {
            ! $hasDeposit => BookingPaymentKind::Deposit,
            ! $hasBalance => BookingPaymentKind::Balance,
            default => BookingPaymentKind::Additional,
        };

        $amount = $kind === BookingPaymentKind::Deposit
            ? ($booking->deposit_cents ?? $booking->totalCents())
            : $booking->balanceDueCents();

        if ($amount <= 0) {
            return back()->with('success', 'Nothing is currently due on this reservation.');
        }

        $booking->payments()->create([
            'kind' => $kind,
            'amount_cents' => $amount,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        if ($booking->status === BookingStatus::PendingPayment) {
            $booking->confirm();
        }

        return back()->with('success', ucfirst($kind->value)." payment of $".number_format($amount / 100, 2).' verified.');
    }

    public function cancel(Booking $booking, Request $request): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:cancel'],
        ]);

        $booking->cancel();

        return redirect()->route('reservations.index')->with('success', 'Reservation cancelled.');
    }

    public function sendReservationReminder(Booking $booking): RedirectResponse
    {
        $booking->loadMissing('guest', 'room.roomType');

        Mail::to($booking->guest->email)->send(new ReservationReminderMail($booking));

        $booking->update(['last_reminder_sent_at' => now(), 'last_reminder_type' => 'reservation']);

        return back()->with('success', 'Reservation reminder sent to '.$booking->guest->email.'.');
    }

    public function sendPaymentReminder(Booking $booking): RedirectResponse
    {
        $booking->loadMissing('guest', 'room.roomType', 'charges', 'payments');

        $balanceDueCents = $booking->balanceDueCents();

        if ($balanceDueCents <= 0) {
            return back()->with('success', 'Nothing is currently due — no payment reminder sent.');
        }

        Mail::to($booking->guest->email)->send(new PaymentReminderMail($booking, $balanceDueCents));

        $booking->update(['last_reminder_sent_at' => now(), 'last_reminder_type' => 'payment']);

        return back()->with('success', 'Payment reminder sent to '.$booking->guest->email.'.');
    }

    public function previewDateChange(Booking $booking, Request $request): JsonResponse
    {
        $data = $request->validate([
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        $nights = $checkIn->diffInDays($checkOut);

        $booking->loadMissing('room.roomType', 'charges', 'payments');
        $currentTotalCents = $booking->totalCents();
        $amountPaidCents = $booking->amountPaidCents();

        $sameRoomAvailable = $this->roomIsAvailable($booking->room_id, $checkIn, $checkOut, $booking->id);
        $sameRoomTotalCents = $nights * $booking->room->roomType->base_rate_cents;

        $currentRoomOption = [
            'room_id' => $booking->room_id,
            'room_number' => $booking->room->number,
            'room_type_id' => $booking->room->room_type_id,
            'room_type_name' => $booking->room->roomType->name,
            'available' => $sameRoomAvailable,
            'total_cents' => $sameRoomTotalCents,
            'delta_cents' => $sameRoomTotalCents - $currentTotalCents,
            'blocked' => $sameRoomTotalCents < $amountPaidCents,
        ];

        $alternateRooms = [];

        if (! $sameRoomAvailable) {
            $rooms = Room::with('roomType')
                ->where('status', RoomStatus::Active->value)
                ->where('id', '!=', $booking->room_id)
                ->orderByRaw('CASE WHEN room_type_id = ? THEN 0 ELSE 1 END', [$booking->room->room_type_id])
                ->orderBy('number')
                ->get();

            foreach ($rooms as $room) {
                if (! $this->roomIsAvailable($room->id, $checkIn, $checkOut, $booking->id)) {
                    continue;
                }

                $totalCents = $nights * $room->roomType->base_rate_cents;

                $alternateRooms[] = [
                    'room_id' => $room->id,
                    'room_number' => $room->number,
                    'room_type_id' => $room->room_type_id,
                    'room_type_name' => $room->roomType->name,
                    'available' => true,
                    'total_cents' => $totalCents,
                    'delta_cents' => $totalCents - $currentTotalCents,
                    'blocked' => $totalCents < $amountPaidCents,
                ];
            }
        }

        return response()->json([
            'nights' => $nights,
            'current_total_cents' => $currentTotalCents,
            'amount_paid_cents' => $amountPaidCents,
            'current_room_option' => $currentRoomOption,
            'alternate_rooms' => $alternateRooms,
        ]);
    }

    public function applyDateChange(Booking $booking, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        $nights = $checkIn->diffInDays($checkOut);
        $room = Room::with('roomType')->findOrFail($data['room_id']);

        if (! $this->roomIsAvailable($room->id, $checkIn, $checkOut, $booking->id)) {
            return back()->withErrors(['check_in' => 'That room is no longer available for those dates.']);
        }

        $newTotalCents = $nights * $room->roomType->base_rate_cents;
        $currentTotalCents = $booking->totalCents();
        $amountPaidCents = $booking->amountPaidCents();

        if ($newTotalCents < $amountPaidCents) {
            return back()->withErrors([
                'check_in' => 'That change would bring the total below the $'.number_format($amountPaidCents / 100, 2).' already paid. Choose different dates or a different room.',
            ]);
        }

        $roomChanged = $room->id !== $booking->room_id;
        $datesChanged = ! $checkIn->equalTo($booking->check_in) || ! $checkOut->equalTo($booking->check_out);

        if (! $roomChanged && ! $datesChanged) {
            return back()->with('success', 'No changes to apply.');
        }

        $descriptionParts = [];

        if ($roomChanged) {
            $descriptionParts[] = "Room changed to {$room->roomType->name} {$room->number}";
        }

        if ($datesChanged) {
            $descriptionParts[] = "Dates changed to {$checkIn->toFormattedDateString()} – {$checkOut->toFormattedDateString()}";
        }

        try {
            DB::transaction(function () use ($booking, $room, $checkIn, $checkOut, $newTotalCents, $currentTotalCents, $roomChanged, $descriptionParts) {
                $booking->update([
                    'room_id' => $room->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                ]);

                $booking->charges()->create([
                    'category' => $roomChanged ? BookingChargeCategory::RoomChange : BookingChargeCategory::DateChange,
                    'description' => implode(', ', $descriptionParts),
                    'amount_cents' => $newTotalCents - $currentTotalCents,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23P01') {
                return back()->withErrors(['check_in' => 'That room is no longer available for those dates.']);
            }

            throw $exception;
        }

        return redirect()->route('reservations.show', $booking)->with('success', 'Reservation updated.');
    }

    private function roomIsAvailable(int $roomId, Carbon $checkIn, Carbon $checkOut, ?int $excludingBookingId = null): bool
    {
        return ! Booking::where('room_id', $roomId)
            ->when($excludingBookingId, fn ($query) => $query->where('id', '!=', $excludingBookingId))
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::NoShow->value])
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
    }

    /**
     * Deletes any walk-in draft (a pending_payment booking with no guest
     * attached yet — see "Walk-in reservation creation" in the domain
     * plan) whose lock has expired, so the room becomes searchable again
     * without needing a scheduled job.
     */
    private function sweepExpiredDrafts(): void
    {
        Booking::whereNull('guest_id')
            ->where('status', BookingStatus::PendingPayment)
            ->where('expires_at', '<', now())
            ->delete();
    }

    private function isLiveDraft(Booking $booking): bool
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
}
