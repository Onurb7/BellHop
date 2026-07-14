<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:staff|admin|super-admin'])
    ->group(function () {
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

        Route::prefix('reservations')->name('reservations.')->group(function () {
            Route::get('/', [ReservationController::class, 'index'])->name('index');
            Route::get('/{booking}', [ReservationController::class, 'show'])->name('show');
            Route::post('/{booking}/verify-payment', [ReservationController::class, 'verifyPayment'])->name('verify-payment');
            Route::post('/{booking}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
            Route::post('/{booking}/remind/reservation', [ReservationController::class, 'sendReservationReminder'])->name('remind.reservation');
            Route::post('/{booking}/remind/payment', [ReservationController::class, 'sendPaymentReminder'])->name('remind.payment');
            Route::post('/{booking}/date-change/preview', [ReservationController::class, 'previewDateChange'])->name('date-change.preview');
            Route::post('/{booking}/date-change/apply', [ReservationController::class, 'applyDateChange'])->name('date-change.apply');
        });
    });
