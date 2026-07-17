<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\BookingCancelledNonPaymentMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CancelUnpaidBalances extends Command
{
    protected $signature = 'bookings:cancel-unpaid-balances';

    protected $description = 'Cancel confirmed bookings whose balance is still unpaid 24h after collection failed (declined charge or no saved card), freeing the room to resell';

    public function handle(): int
    {
        // (assumption: 24h, tune later) — long enough to give the guest a
        // real chance to react to the failure/reminder email from
        // bookings:charge-due-balances, short enough to still leave a
        // window to resell the room before check-in.
        $bookings = Booking::where('status', BookingStatus::Confirmed)
            ->whereNotNull('balance_collection_failed_at')
            ->where('balance_collection_failed_at', '<=', now()->subHours(24))
            ->with('guest')
            ->get()
            ->filter(fn (Booking $booking) => $booking->balanceDueCents() > 0);

        foreach ($bookings as $booking) {
            $booking->cancel();
            Mail::to($booking->guest->email)->send(new BookingCancelledNonPaymentMail($booking));
        }

        $this->info("Cancelled {$bookings->count()} unpaid booking(s).");

        return self::SUCCESS;
    }
}
