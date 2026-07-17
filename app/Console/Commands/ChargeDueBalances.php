<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\PaymentAutoChargeFailedMail;
use App\Mail\PaymentReminderMail;
use App\Models\Booking;
use App\Services\StripePaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\CardException;

class ChargeDueBalances extends Command
{
    protected $signature = 'bookings:charge-due-balances';

    protected $description = 'Off-session charge (or, without a saved card, remind about) the remaining balance for bookings due 3 days before check-in';

    public function handle(StripePaymentService $stripe): int
    {
        $bookings = Booking::where('status', BookingStatus::Confirmed)
            ->whereNotNull('balance_due_at')
            ->where('balance_due_at', '<=', now())
            ->with('guest')
            ->get()
            ->filter(fn (Booking $booking) => $booking->balanceDueCents() > 0);

        $charged = 0;
        $reminded = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            $amountCents = $booking->balanceDueCents();

            // No saved card (the guest didn't opt in at deposit time) —
            // nothing to charge, so this is the reminder path instead.
            if (! $booking->stripe_payment_method_id) {
                $booking->update(['balance_due_at' => null, 'balance_collection_failed_at' => now()]);
                Mail::to($booking->guest->email)->send(new PaymentReminderMail($booking, $amountCents, willAutoCancel: true));
                $reminded++;

                continue;
            }

            try {
                // Records itself via the normal payment_intent.succeeded
                // webhook, exactly like any other payment — nothing more
                // to do here on success.
                $stripe->chargeOffSession($booking, $amountCents);
                $charged++;
            } catch (CardException) {
                // No retry — bookings:cancel-unpaid-balances is the
                // backstop if this stays unpaid past its grace window.
                $booking->update(['balance_due_at' => null, 'balance_collection_failed_at' => now()]);
                Mail::to($booking->guest->email)->send(new PaymentAutoChargeFailedMail(
                    $booking,
                    $amountCents,
                    route('guest-reservations.show', $booking),
                ));
                $failed++;
            }
        }

        $this->info("Charged {$charged} balance(s), reminded {$reminded}, {$failed} failed.");

        return self::SUCCESS;
    }
}
