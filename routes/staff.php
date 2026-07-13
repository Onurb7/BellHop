<?php

use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:staff|admin|super-admin'])
    ->group(function () {
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
    });
