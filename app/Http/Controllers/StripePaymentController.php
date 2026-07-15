<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\StripePaymentService;
use Illuminate\Http\RedirectResponse;

class StripePaymentController extends Controller
{
    public function refund(Booking $booking, BookingPayment $payment, StripePaymentService $stripe): RedirectResponse
    {
        abort_unless($payment->booking_id === $booking->id, 404);
        abort_if($payment->stripe_payment_intent_id === null, 422, 'This payment was not collected via Stripe and cannot be refunded here.');

        $alreadyRefunded = $booking->payments()
            ->where('kind', 'refund')
            ->where('stripe_payment_intent_id', $payment->stripe_payment_intent_id)
            ->exists();

        abort_if($alreadyRefunded, 422, 'This payment has already been refunded.');

        $stripe->refund($payment);

        return back()->with('success', 'Refund submitted — it will appear on the reservation once Stripe confirms it.');
    }
}
