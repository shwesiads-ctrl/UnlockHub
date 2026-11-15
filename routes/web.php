<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\FundsController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\StatementController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Auth\ChallengeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
Route::post('/services/{service}/order', [OrderController::class, 'store'])
    ->middleware(['auth', 'active', 'throttle:30,1'])->name('orders.store');

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

    Route::get('/funds', [FundsController::class, 'index'])->name('funds.index');
    Route::post('/funds', [FundsController::class, 'store'])->name('funds.store')->middleware('throttle:10,1');
    Route::get('/funds/{paymentRequest}/receipt', [FundsController::class, 'receipt'])->name('funds.receipt');
    Route::get('/statement', StatementController::class)->name('statement');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

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

    Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::resource('services', Admin\ServiceController::class)->except('show');
    Route::post('/services/{service}/toggle', [Admin\ServiceController::class, 'toggle'])->name('services.toggle');

    Route::resource('suppliers', Admin\SupplierController::class)->except('show');
    Route::post('/suppliers/{supplier}/test', [Admin\SupplierController::class, 'test'])->name('suppliers.test');
    Route::get('/suppliers/{supplier}/services', [Admin\SupplierController::class, 'services'])->name('suppliers.services');
    Route::post('/suppliers/{supplier}/import', [Admin\SupplierController::class, 'import'])->name('suppliers.import');
    Route::post('/suppliers/{supplier}/sync-costs', [Admin\SupplierController::class, 'syncCosts'])->name('suppliers.sync-costs');

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/manual', [Admin\OrderController::class, 'manual'])->name('orders.manual');
    Route::post('/orders/bulk-in-process', [Admin\OrderController::class, 'bulkInProcess'])->name('orders.bulk-in-process');
    Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');

    Route::get('/payments', [Admin\PaymentRequestController::class, 'index'])->name('payments.index');
    Route::get('/payments/{paymentRequest}', [Admin\PaymentRequestController::class, 'show'])->name('payments.show');
    Route::get('/payments/{paymentRequest}/receipt', [Admin\PaymentRequestController::class, 'receipt'])->name('payments.receipt');
    Route::post('/payments/{paymentRequest}/approve', [Admin\PaymentRequestController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{paymentRequest}/reject', [Admin\PaymentRequestController::class, 'reject'])->name('payments.reject');

    Route::get('/payment-methods', [Admin\PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('/payment-methods', [Admin\PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::put('/payment-methods/{paymentMethod}', [Admin\PaymentMethodController::class, 'update'])->name('payment-methods.update');
});
