<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'message' => 'Vue 3 + Inertia is wired up and rendering through Docker.',
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

require __DIR__.'/auth.php';
