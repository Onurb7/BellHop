<?php

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Deliberately outside the `auth` group — Stripe calls this directly,
// authenticated by signature verification inside the controller, not a
// session. CSRF is exempted for this path in bootstrap/app.php.
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
