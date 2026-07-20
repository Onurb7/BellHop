<?php

namespace App\Console\Commands;

use App\Mail\ReviewFollowupMail;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReviewFollowups extends Command
{
    protected $signature = 'reviews:send-followups';

    protected $description = 'Email the review-invitation link for checked-out bookings whose 3-day follow-up is due';

    public function handle(): int
    {
        $reviews = Review::whereNull('sent_at')
            ->where('send_at', '<=', now())
            ->with('booking.guest', 'booking.room.roomType')
            ->get();

        foreach ($reviews as $review) {
            Mail::to($review->booking->guest->email)->send(new ReviewFollowupMail($review));
            $review->update(['sent_at' => now()]);
        }

        $this->info("Sent {$reviews->count()} review follow-up(s).");

        return self::SUCCESS;
    }
}
