<?php

namespace App\Http\Controllers;

use App\Enums\WeekStart;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $view = in_array($request->string('view')->value(), ['day', 'week', 'month'])
            ? $request->string('view')->value()
            : 'week';

        $anchor = $request->filled('date')
            ? Carbon::parse($request->string('date')->value())
            : Carbon::today();

        $weekStart = $request->user()->getSetting('week_start', WeekStart::Monday->value) === WeekStart::Sunday->value
            ? Carbon::SUNDAY
            : Carbon::MONDAY;

        [$rangeStart, $rangeEnd] = match ($view) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->addDay()->startOfDay()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->startOfMonth()->addMonth()],
            default => [$anchor->copy()->startOfWeek($weekStart), $anchor->copy()->startOfWeek($weekStart)->addWeek()],
        };

        $floor = $request->string('floor')->value() ?: null;

        $roomsQuery = Room::with('roomType')->orderBy('number');

        if ($floor) {
            $roomsQuery->where('floor', $floor);
        }

        $rooms = $roomsQuery->get();

        // check_out >= rangeStart (not strictly >) so a booking whose
        // departure lands exactly on the first visible day is still
        // included — the tape chart needs it to render that day's "OUT"
        // half, even though that date is outside the booking's occupied
        // [check_in, check_out) range.
        $bookings = Booking::with('guest')
            ->whereIn('room_id', $rooms->pluck('id'))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in', '<', $rangeEnd)
            ->where('check_out', '>=', $rangeStart)
            ->get();

        return Inertia::render('Calendar/Index', [
            'view' => $view,
            'date' => $anchor->toDateString(),
            'floor' => $floor,
            'floors' => Room::query()->select('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'title' => $room->title,
                'room_type' => $room->roomType->name,
            ]),
            'bookings' => $bookings->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'room_id' => $booking->room_id,
                'guest_name' => $booking->guest->name,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'status' => $booking->status->value,
            ]),
        ]);
    }
}
