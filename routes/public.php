<?php

use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\RoomCatalogController;
use Illuminate\Support\Facades\Route;

// No `auth` middleware anywhere here — reachable by anyone, including an
// already-logged-in guest who wants to book again. State-mutating steps
// are throttled to blunt scripted abuse of the room-locking mechanism.

Route::get('rooms', [RoomCatalogController::class, 'index'])->name('rooms.index');
Route::get('rooms/{room}', [RoomCatalogController::class, 'show'])->name('rooms.show');

Route::post('book/lock', [BookingController::class, 'lock'])
    ->middleware('throttle:10,1')
    ->name('booking.lock');
Route::get('book/{booking}', [BookingController::class, 'show'])->name('booking.show');
Route::post('book/{booking}/guest', [BookingController::class, 'storeGuest'])
    ->middleware('throttle:10,1')
    ->name('booking.guest.store');
Route::post('book/{booking}/promo-code/preview', [BookingController::class, 'previewPromoCode'])
    ->middleware('throttle:20,1')
    ->name('booking.promo-code.preview');
Route::delete('book/{booking}', [BookingController::class, 'abandon'])->name('booking.abandon');
Route::post('book/{booking}/stripe/intent', [BookingController::class, 'createPaymentIntent'])
    ->middleware('throttle:10,1')
    ->name('booking.stripe.intent');

// Signed only — see BookingController::confirmation() for why a bare
// booking ID isn't enough here.
Route::get('book/{booking}/confirmation', [BookingController::class, 'confirmation'])
    ->middleware('signed')
    ->name('booking.confirmation');
