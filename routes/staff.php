<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\StripePaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:staff|admin|super-admin'])
    ->group(function () {
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

        Route::prefix('reservations')->name('reservations.')->group(function () {
            // Registered before the /{booking} routes below — otherwise
            // Laravel's route-model binding tries to resolve "new" as a
            // Booking ID and 404s.
            Route::get('/new', [ReservationController::class, 'newSearch'])->name('new.search');
            Route::post('/new/lock', [ReservationController::class, 'lock'])->name('new.lock');
            Route::get('/new/{booking}/guest', [ReservationController::class, 'newGuestForm'])->name('new.guest');
            Route::post('/new/{booking}/guest', [ReservationController::class, 'storeGuest'])->name('new.guest.store');
            Route::post('/new/{booking}/promo-code/preview', [ReservationController::class, 'previewPromoCode'])->name('new.promo-code.preview');
            Route::delete('/new/{booking}', [ReservationController::class, 'abandon'])->name('new.abandon');

            Route::get('/', [ReservationController::class, 'index'])->name('index');
            Route::get('/{booking}', [ReservationController::class, 'show'])->name('show');
            Route::post('/{booking}/services', [ReservationController::class, 'addService'])->name('services.store');
            Route::post('/{booking}/verify-payment', [ReservationController::class, 'verifyPayment'])->name('verify-payment');
            Route::post('/{booking}/payments/{payment}/refund', [StripePaymentController::class, 'refund'])->name('payments.refund');
            Route::post('/{booking}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
            Route::post('/{booking}/check-in', [ReservationController::class, 'checkIn'])->name('check-in');
            Route::post('/{booking}/check-out', [ReservationController::class, 'checkOut'])->name('check-out');
            Route::post('/{booking}/remind/reservation', [ReservationController::class, 'sendReservationReminder'])->name('remind.reservation');
            Route::post('/{booking}/remind/payment', [ReservationController::class, 'sendPaymentReminder'])->name('remind.payment');
            Route::post('/{booking}/date-change/preview', [ReservationController::class, 'previewDateChange'])->name('date-change.preview');
            Route::post('/{booking}/date-change/apply', [ReservationController::class, 'applyDateChange'])->name('date-change.apply');
        });
    });
