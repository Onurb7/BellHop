<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentAutoChargeFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public int $balanceDueCents, public string $payUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We couldn't charge your card for your upcoming stay",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-auto-charge-failed',
            with: [
                'booking' => $this->booking,
                'balanceDueCents' => $this->balanceDueCents,
                'payUrl' => $this->payUrl,
            ],
        );
    }
}
