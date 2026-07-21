<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public int $balanceDueCents, public bool $willAutoCancel = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment due for your Bellhop reservation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-reminder',
            with: [
                'booking' => $this->booking,
                'balanceDueCents' => $this->balanceDueCents,
                'willAutoCancel' => $this->willAutoCancel,
            ],
        );
    }
}
