<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /**
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = ['submitted_at', 'rating', 'featured'];

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->value();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : null;
        $dir = $request->string('dir')->value() === 'asc' ? 'asc' : 'desc';

        $reviews = Review::with('booking.guest', 'booking.room.roomType')
            ->when(
                $sort,
                // NULLS LAST regardless of direction — a review with no
                // rating/submitted_at yet (still pending/sent) should
                // never jump to the top just because "ascending" was
                // clicked. $sort is constrained to SORTABLE_COLUMNS
                // above, never raw user input, before it reaches here.
                fn ($query) => $query->orderByRaw("{$sort} {$dir} NULLS LAST"),
                fn ($query) => $query->latest(),
            )
            ->paginate(15)
            ->withQueryString();

        $reviews->through(fn (Review $review) => [
            'id' => $review->id,
            'guest_name' => $review->booking->guest->name,
            'room_type' => $review->booking->room->roomType->name,
            'check_in' => $review->booking->check_in->toDateString(),
            'check_out' => $review->booking->check_out->toDateString(),
            'rating' => $review->rating,
            'body' => $review->body,
            'submitted_at' => $review->submitted_at?->toIso8601String(),
            'status' => match (true) {
                $review->submitted_at !== null => 'reviewed',
                $review->sent_at !== null => 'sent',
                default => 'pending',
            },
            'featured' => $review->featured,
        ]);

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function toggleFeatured(Review $review): RedirectResponse
    {
        abort_if($review->submitted_at === null, 422, "Can't feature a review that hasn't been submitted yet.");

        $review->update(['featured' => ! $review->featured]);

        return back()->with('success', $review->featured ? 'Review featured.' : 'Review unfeatured.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return redirect()->route('admin.reviews.index')->with('success', 'Review deleted.');
    }
}
