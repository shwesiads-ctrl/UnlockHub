<?php

use Illuminate\Support\Facades\Route;

/*
| Reseller API (DHRU-compatible) — added in step 6.
*/

Route::get('/ping', fn () => ['status' => 'ok']);
