<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Auth\ChallengeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/* ---------------- Guest: register / login / password reset ---------------- */
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/login/verify', [ChallengeController::class, 'show'])->name('login.challenge');
    Route::post('/login/verify', [ChallengeController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/login/verify/resend', [ChallengeController::class, 'resend'])->name('login.challenge.resend')->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/* ---------------- Signed-in user ---------------- */
Route::middleware(['auth', 'active'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/security', [SecurityController::class, 'show'])->name('security');
    Route::put('/security/profile', [SecurityController::class, 'updateProfile'])->name('security.profile');
    Route::put('/security/password', [SecurityController::class, 'updatePassword'])->name('security.password');
    Route::post('/security/2fa', [SecurityController::class, 'startTwoFactor'])->name('security.2fa.start');
    Route::post('/security/2fa/confirm', [SecurityController::class, 'confirmTwoFactor'])->name('security.2fa.confirm');
    Route::delete('/security/2fa', [SecurityController::class, 'disableTwoFactor'])->name('security.2fa.disable');
    Route::delete('/security/devices/{device}', [SecurityController::class, 'forgetDevice'])->name('security.devices.forget');
    Route::delete('/security/devices', [SecurityController::class, 'forgetAllDevices'])->name('security.devices.forget-all');
});

/* ---------------- Admin ---------------- */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
});
