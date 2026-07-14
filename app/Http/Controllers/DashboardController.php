<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\WeekStart;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['staff', 'admin', 'super-admin'])) {
            return Inertia::render('Dashboard', [
                'reservations' => $this->guestReservations($user),
            ]);
        }

        $view = in_array($request->string('view')->value(), ['day', 'week', 'month'])
            ? $request->string('view')->value()
            : 'day';

        $weekStartDay = $user->getSetting('week_start', WeekStart::Monday->value) === WeekStart::Sunday->value
            ? Carbon::SUNDAY
            : Carbon::MONDAY;

        $totalRooms = Room::where('status', RoomStatus::Active->value)->count();
        $today = Carbon::today();
        $nonHoldingStatuses = [BookingStatus::Cancelled->value, BookingStatus::NoShow->value];

        $todayCounts = $this->occupancyByDay($today, $today->copy()->addDay());
        $occupiedToday = $todayCounts[$today->toDateString()] ?? 0;

        return Inertia::render('Dashboard', [
            'capacity' => [
                'view' => $view,
                'total_rooms' => $totalRooms,
                'kpis' => [
                    'occupied_today' => $occupiedToday,
                    'occupancy_today_pct' => $totalRooms > 0 ? round($occupiedToday / $totalRooms * 100, 1) : 0.0,
                    'check_ins_today' => Booking::whereDate('check_in', $today)->whereNotIn('status', $nonHoldingStatuses)->count(),
                    'check_outs_today' => Booking::whereDate('check_out', $today)->whereNotIn('status', $nonHoldingStatuses)->count(),
                    'pending_payment_count' => Booking::where('status', BookingStatus::PendingPayment->value)->count(),
                ],
                'trend' => $this->trendSeries($view, $totalRooms, $weekStartDay),
                'by_weekday' => $this->weekdayStats($totalRooms, $weekStartDay),
                'by_day_of_month' => $this->dayOfMonthStats($totalRooms),
            ],
        ]);
    }

    /**
     * Null when this guest account has no linked `guests` row yet (no
     * stay has ever been attached to it) — the page renders a
     * "no reservations yet" placeholder in that case rather than empty
     * arrays, so the two states stay visually distinct.
     */
    private function guestReservations(User $user): ?array
    {
        $guest = $user->guest;

        if (! $guest) {
            return null;
        }

        $activeStatuses = [
            BookingStatus::PendingPayment->value,
            BookingStatus::Confirmed->value,
            BookingStatus::CheckedIn->value,
        ];

        $bookings = $guest->bookings()
            ->with('room.roomType')
            ->withSum('charges as total_cents', 'amount_cents')
            ->withSum('payments as amount_paid_cents', 'amount_cents')
            ->get()
            ->map(function (Booking $booking) {
                $totalCents = (int) ($booking->total_cents ?? 0);
                $paidCents = (int) ($booking->amount_paid_cents ?? 0);

                return [
                    'id' => $booking->id,
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

        return [
            'active' => $bookings->whereIn('status', $activeStatuses)
                ->sortBy('check_in')
                ->values()
                ->all(),
            'past' => $bookings->where('status', BookingStatus::CheckedOut->value)
                ->sortByDesc('check_out')
                ->values()
                ->all(),
        ];
    }

    /**
     * Occupied-room count per day in [$start, $end) — one query, bucketed
     * in PHP (clip each booking to the window, walk day by day). Trivial
     * at boutique-hotel booking volumes; no need for generate_series SQL.
     */
    private function occupancyByDay(Carbon $start, Carbon $end): array
    {
        $bookings = Booking::whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::NoShow->value])
            ->where('check_in', '<', $end)
            ->where('check_out', '>', $start)
            ->get(['check_in', 'check_out']);

        $counts = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $counts[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }

        foreach ($bookings as $booking) {
            $day = $booking->check_in->lt($start) ? $start->copy() : $booking->check_in->copy();
            $last = $booking->check_out->gt($end) ? $end->copy() : $booking->check_out->copy();

            while ($day->lt($last)) {
                $key = $day->toDateString();

                if (array_key_exists($key, $counts)) {
                    $counts[$key]++;
                }

                $day->addDay();
            }
        }

        return $counts;
    }

    private function trendSeries(string $view, int $totalRooms, int $weekStartDay): array
    {
        return match ($view) {
            'week' => $this->weeklyTrend($totalRooms, $weekStartDay),
            'month' => $this->monthlyTrend($totalRooms),
            default => $this->dailyTrend($totalRooms),
        };
    }

    private function dailyTrend(int $totalRooms): array
    {
        $start = Carbon::today()->subDays(15);
        $end = Carbon::today()->addDays(15);
        $counts = $this->occupancyByDay($start, $end);

        return collect($counts)
            ->map(fn ($count, $date) => [
                'label' => $date,
                'value' => $totalRooms > 0 ? round($count / $totalRooms * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    private function weeklyTrend(int $totalRooms, int $weekStartDay): array
    {
        $start = Carbon::today()->startOfWeek($weekStartDay)->subWeeks(6);
        $end = Carbon::today()->startOfWeek($weekStartDay)->addWeeks(6);
        $counts = $this->occupancyByDay($start, $end);

        $weeks = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $daySum = 0;
            $dayTotal = 0;

            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->copy()->addDays($i)->toDateString();

                if (array_key_exists($dateKey, $counts)) {
                    $daySum += $counts[$dateKey];
                    $dayTotal++;
                }
            }

            $weeks[] = [
                'label' => $cursor->toDateString(),
                'value' => ($totalRooms > 0 && $dayTotal > 0) ? round(($daySum / $dayTotal) / $totalRooms * 100, 1) : 0.0,
            ];

            $cursor->addWeek();
        }

        return $weeks;
    }

    private function monthlyTrend(int $totalRooms): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths(6);
        $end = Carbon::today()->startOfMonth()->addMonths(6);
        $counts = $this->occupancyByDay($start, $end->copy()->endOfMonth()->addDay());

        $months = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $monthEnd = $cursor->copy()->addMonth();
            $daySum = 0;
            $dayTotal = 0;
            $day = $cursor->copy();

            while ($day->lt($monthEnd)) {
                $dateKey = $day->toDateString();

                if (array_key_exists($dateKey, $counts)) {
                    $daySum += $counts[$dateKey];
                    $dayTotal++;
                }

                $day->addDay();
            }

            $months[] = [
                'label' => $cursor->format('Y-m'),
                'value' => ($totalRooms > 0 && $dayTotal > 0) ? round(($daySum / $dayTotal) / $totalRooms * 100, 1) : 0.0,
            ];

            $cursor->addMonth();
        }

        return $months;
    }

    private function weekdayStats(int $totalRooms, int $weekStartDay): array
    {
        $start = Carbon::today()->subDays(60);
        $end = Carbon::today()->addDays(30);
        $counts = $this->occupancyByDay($start, $end);

        $buckets = array_fill(0, 7, ['sum' => 0, 'count' => 0]);

        foreach ($counts as $dateKey => $occupied) {
            $index = Carbon::parse($dateKey)->dayOfWeekIso - 1; // 0 Monday .. 6 Sunday
            $buckets[$index]['sum'] += $occupied;
            $buckets[$index]['count']++;
        }

        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $order = $weekStartDay === Carbon::SUNDAY ? [6, 0, 1, 2, 3, 4, 5] : [0, 1, 2, 3, 4, 5, 6];

        return collect($order)
            ->map(function ($index) use ($buckets, $labels, $totalRooms) {
                $bucket = $buckets[$index];
                $avgOccupied = $bucket['count'] > 0 ? $bucket['sum'] / $bucket['count'] : 0;

                return [
                    'label' => $labels[$index],
                    'value' => $totalRooms > 0 ? round($avgOccupied / $totalRooms * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    private function dayOfMonthStats(int $totalRooms): array
    {
        $start = Carbon::today()->subDays(60);
        $end = Carbon::today()->addDays(30);
        $counts = $this->occupancyByDay($start, $end);

        $buckets = array_fill(1, 31, ['sum' => 0, 'count' => 0]);

        foreach ($counts as $dateKey => $occupied) {
            $day = Carbon::parse($dateKey)->day;
            $buckets[$day]['sum'] += $occupied;
            $buckets[$day]['count']++;
        }

        $result = [];

        for ($day = 1; $day <= 31; $day++) {
            $bucket = $buckets[$day];

            if ($bucket['count'] === 0) {
                continue;
            }

            $result[] = [
                'label' => (string) $day,
                'value' => $totalRooms > 0 ? round(($bucket['sum'] / $bucket['count']) / $totalRooms * 100, 1) : 0.0,
            ];
        }

        return $result;
    }
}
