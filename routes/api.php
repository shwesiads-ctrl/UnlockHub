<?php

use App\Http\Controllers\Api\DhruApiController;
use Illuminate\Support\Facades\Route;

/*
| Reseller API — DHRU Fusion compatible.
| DHRU clients add "/api/index.php" to the site URL, so this route matches that path.
*/

Route::post('/index.php', DhruApiController::class)->middleware('throttle:120,1')->name('api.dhru');
Route::post('/', DhruApiController::class)->middleware('throttle:120,1');

// Card / PayPal webhooks (no CSRF; payment state is always re-read from the gateway API)
Route::post('/payments/stripe/webhook', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'stripe'])->middleware('throttle:120,1')->name('webhooks.stripe');
Route::post('/payments/paypal/webhook', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'paypal'])->middleware('throttle:120,1')->name('webhooks.paypal');

Route::get('/ping', fn () => ['status' => 'ok']);
