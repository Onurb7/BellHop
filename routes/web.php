<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestReservationController;
use App\Http\Controllers\InvoiceController;
use App\Models\Review;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'message' => 'New here? Book your stay. Already a guest? Enter the lobby.',
        // Only public-safe fields — never the guest's email, and only a
        // first-name + last-initial, never the full name.
        'featuredReviews' => Review::where('featured', true)
            ->whereNotNull('submitted_at')
            ->with('booking.guest')
            ->latest('submitted_at')
            ->limit(6)
            ->get()
            ->map(fn (Review $review) => [
                'rating' => $review->rating,
                'body' => $review->body,
                'guest_name' => trim($review->booking->guest->first_name.' '.mb_substr($review->booking->guest->last_name, 0, 1).'.'),
            ]),
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Guest-facing view of a single reservation — authorized by
    // booking ownership inside the controller, not a role, since any
    // authenticated account (not just `guest`-role ones) could in
    // principle be linked to a `guests` row.
    Route::get('my-reservations/{booking}', [GuestReservationController::class, 'show'])->name('guest-reservations.show');
    Route::post('my-reservations/{booking}/stripe/intent', [GuestReservationController::class, 'createPaymentIntent'])->name('guest-reservations.stripe.intent');
    Route::post('my-reservations/{booking}/services', [GuestReservationController::class, 'purchaseService'])->name('guest-reservations.services.store');

    Route::get('invoices/{booking}', [InvoiceController::class, 'download'])->name('invoices.download');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/staff.php';
require __DIR__.'/settings.php';
require __DIR__.'/profile.php';
require __DIR__.'/webhooks.php';
require __DIR__.'/public.php';
