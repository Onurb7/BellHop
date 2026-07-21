<?php

namespace App\Mail;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewFollowupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Review $review) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'How was your stay?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.review-followup',
            with: [
                'review' => $this->review,
                'booking' => $this->review->booking,
                'reviewUrl' => url("/review/{$this->review->uuid}"),
            ],
        );
    }
}
