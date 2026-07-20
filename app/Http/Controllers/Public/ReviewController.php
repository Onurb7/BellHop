<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function show(Review $review): Response
    {
        $review->loadMissing('booking.room.roomType');

        return Inertia::render('Public/Review/Show', [
            'review' => [
                'uuid' => $review->uuid,
                'rating' => $review->rating,
                'body' => $review->body,
                'already_submitted' => $review->submitted_at !== null,
            ],
            'booking' => [
                'room_type' => $review->booking->room->roomType->name,
                'check_in' => $review->booking->check_in->toDateString(),
                'check_out' => $review->booking->check_out->toDateString(),
            ],
        ]);
    }

    public function store(Review $review, Request $request): RedirectResponse
    {
        abort_if($review->submitted_at !== null, 422, 'This review has already been submitted.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update([
            'rating' => $data['rating'],
            'body' => $data['body'] ?? null,
            'submitted_at' => now(),
        ]);

        return redirect()->route('reviews.show', $review);
    }
}
