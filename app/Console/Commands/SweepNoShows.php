<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;

class SweepNoShows extends Command
{
    protected $signature = 'bookings:sweep-no-shows';

    protected $description = 'Mark confirmed bookings whose check-in has passed with no check-in as no-shows';

    public function handle(): int
    {
        $bookings = Booking::where('status', BookingStatus::Confirmed)
            ->where('check_in', '<', today())
            ->get();

        $bookings->each(fn (Booking $booking) => $booking->markNoShow());

        $this->info("Marked {$bookings->count()} booking(s) as no-show.");

        return self::SUCCESS;
    }
}
