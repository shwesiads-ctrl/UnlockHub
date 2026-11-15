<?php

use App\Http\Controllers\Api\DhruApiController;
use Illuminate\Support\Facades\Route;

/*
| Reseller API — DHRU Fusion compatible.
| DHRU clients add "/api/index.php" to the site URL, so this route matches that path.
*/

Route::post('/index.php', DhruApiController::class)->middleware('throttle:120,1')->name('api.dhru');
Route::post('/', DhruApiController::class)->middleware('throttle:120,1');

Route::get('/ping', fn () => ['status' => 'ok']);
