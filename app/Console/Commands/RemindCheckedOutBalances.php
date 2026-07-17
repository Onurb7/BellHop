<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\PaymentReminderMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class RemindCheckedOutBalances extends Command
{
    protected $signature = 'bookings:remind-checked-out-balances';

    protected $description = 'Send a daily payment reminder for checked-out bookings that still have an unpaid balance';

    public function handle(): int
    {
        $bookings = Booking::where('status', BookingStatus::CheckedOut)
            ->with('guest', 'charges', 'payments')
            ->get()
            ->filter(fn (Booking $booking) => $booking->balanceDueCents() > 0);

        foreach ($bookings as $booking) {
            Mail::to($booking->guest->email)->send(new PaymentReminderMail($booking, $booking->balanceDueCents()));
            $booking->update(['last_reminder_sent_at' => now(), 'last_reminder_type' => 'payment']);
        }

        $this->info("Sent {$bookings->count()} checked-out balance reminder(s).");

        return self::SUCCESS;
    }
}
