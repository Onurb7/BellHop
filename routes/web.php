<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'message' => 'Vue 3 + Inertia is wired up and rendering through Docker.',
    ]);
});
