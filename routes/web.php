<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Public pages
| Auth (step 2), services (3), wallet (4), orders (5) and admin routes
| are added step by step.
*/

Route::get('/', HomeController::class)->name('home');
