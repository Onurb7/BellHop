<?php

use App\Http\Controllers\Admin\AmenityController;
use App\Http\Controllers\Admin\PricingRuleController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin|super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('room-types', RoomTypeController::class)->except('show');

        Route::resource('rooms', RoomController::class)->except('show');
        Route::post('rooms/{room}/duplicate', [RoomController::class, 'duplicate'])->name('rooms.duplicate');

        Route::resource('services', ServiceController::class)->except('show');

        Route::post('amenities', [AmenityController::class, 'store'])->name('amenities.store');

        Route::resource('pricing', PricingRuleController::class)
            ->parameters(['pricing' => 'pricingRule'])
            ->except('show');

        Route::resource('promo-codes', PromoCodeController::class)
            ->parameters(['promo-codes' => 'promoCode'])
            ->except('show');
    });
