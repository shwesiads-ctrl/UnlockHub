<?php

use App\Http\Controllers\Account\ApiAccessController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\FundsController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\PointsController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\StatementController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Auth\ChallengeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(array_key_exists($locale, config('unlockhub.locales')), 404);
    session(['locale' => $locale]);
    auth()->user()?->forceFill(['preferred_locale' => $locale])->save();

    return back();
})->name('locale');

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
Route::post('/services/{service}/order', [OrderController::class, 'store'])
    ->middleware(['auth', 'active', 'throttle:30,1'])->name('orders.store');

/* ---------------- Content pages ---------------- */
Route::get('/kb', [ContentController::class, 'posts'])->defaults('type', 'kb')->name('kb.index');
Route::get('/kb/{slug}', [ContentController::class, 'post'])->defaults('type', 'kb')->name('kb.show');
Route::get('/blog', [ContentController::class, 'posts'])->defaults('type', 'blog')->name('blog.index');
Route::get('/blog/{slug}', [ContentController::class, 'post'])->defaults('type', 'blog')->name('blog.show');
Route::get('/testimonials', [ContentController::class, 'testimonials'])->name('testimonials');
Route::post('/testimonials', [ContentController::class, 'storeTestimonial'])->middleware(['auth', 'active', 'throttle:5,1'])->name('testimonials.store');
Route::get('/downloads', [ContentController::class, 'downloads'])->name('downloads');
Route::get('/downloads/{download}', [ContentController::class, 'download'])->middleware('throttle:30,1')->name('downloads.get');
Route::get('/track-order', [ContentController::class, 'track'])->middleware('throttle:30,1')->name('track');
Route::get('/contact', [ContentController::class, 'contact'])->name('contact');
Route::post('/contact', [ContentController::class, 'sendContact'])->middleware('throttle:5,10')->name('contact.send');
Route::get('/rss', [ContentController::class, 'rss'])->name('rss');

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

/* ---------------- Social login (guest = sign in / register, signed in = connect) ---------------- */
Route::get('/auth/telegram/callback', [SocialAuthController::class, 'telegram'])->middleware('throttle:20,1')->name('social.telegram');
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'facebook', 'discord', 'tiktok'])->middleware('throttle:20,1')->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'facebook', 'discord', 'tiktok'])->middleware('throttle:20,1')->name('social.callback');
Route::delete('/account/social/{provider}', [SocialAuthController::class, 'unlink'])->middleware(['auth', 'active'])->name('social.unlink');

/* ---------------- Signed-in user ---------------- */
Route::middleware(['auth', 'active'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar')->middleware('throttle:10,1');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

    Route::get('/funds', [FundsController::class, 'index'])->name('funds.index');
    Route::post('/funds', [FundsController::class, 'store'])->name('funds.store')->middleware('throttle:10,1');
    Route::get('/funds/{paymentRequest}/receipt', [FundsController::class, 'receipt'])->name('funds.receipt');
    Route::get('/funds/{paymentRequest}', [FundsController::class, 'show'])->name('funds.show');
    Route::get('/statement', StatementController::class)->name('statement');
    Route::get('/statement/transactions/{transaction}/receipt', [StatementController::class, 'receipt'])->name('statement.receipt');
    Route::get('/statement/transactions/{transaction}/receipt/download', [StatementController::class, 'receipt'])->defaults('download', true)->name('statement.receipt.download');
    Route::get('/statement/pdf', [StatementController::class, 'pdf'])->name('statement.pdf')->middleware('throttle:20,1');
    Route::post('/statement/email', [StatementController::class, 'email'])->name('statement.email')->middleware('throttle:5,10');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/api', [ApiAccessController::class, 'show'])->name('api');
    Route::post('/api/key', [ApiAccessController::class, 'generate'])->name('api.generate')->middleware('throttle:5,1');
    Route::put('/api', [ApiAccessController::class, 'update'])->name('api.update');
    Route::delete('/api/key', [ApiAccessController::class, 'revoke'])->name('api.revoke');

    Route::get('/points', [PointsController::class, 'index'])->name('points');
    Route::post('/points/redeem', [PointsController::class, 'redeem'])->name('points.redeem')->middleware('throttle:10,1');
    Route::post('/points/check-in', [PointsController::class, 'checkIn'])->name('points.checkin')->middleware('throttle:10,1');

    Route::get('/favorites', [\App\Http\Controllers\Account\FavoriteController::class, 'index'])->name('favorites');
    Route::post('/favorites/{service}', [\App\Http\Controllers\Account\FavoriteController::class, 'toggle'])->name('favorites.toggle')->middleware('throttle:60,1');
    Route::get('/orders/{order}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');

    Route::get('/tickets', [\App\Http\Controllers\Account\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/new', [\App\Http\Controllers\Account\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [\App\Http\Controllers\Account\TicketController::class, 'store'])->name('tickets.store')->middleware('throttle:10,10');
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Account\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [\App\Http\Controllers\Account\TicketController::class, 'reply'])->name('tickets.reply')->middleware('throttle:20,10');
    Route::post('/tickets/{ticket}/reopen', [\App\Http\Controllers\Account\TicketController::class, 'reopen'])->name('tickets.reopen');
    Route::get('/tickets/{ticket}/files/{attachment}', [\App\Http\Controllers\Account\TicketController::class, 'attachment'])->name('tickets.attachment');

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
Route::middleware(['auth', 'active', 'admin', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/tickets', [Admin\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/saved-replies', [Admin\CannedReplyController::class, 'index'])->name('canned.index');
    Route::post('/tickets/saved-replies', [Admin\CannedReplyController::class, 'store'])->name('canned.store');
    Route::put('/tickets/saved-replies/{canned}', [Admin\CannedReplyController::class, 'update'])->name('canned.update');
    Route::delete('/tickets/saved-replies/{canned}', [Admin\CannedReplyController::class, 'destroy'])->name('canned.destroy');
    Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [Admin\TicketController::class, 'reply'])->name('tickets.reply');
    Route::put('/tickets/{ticket}', [Admin\TicketController::class, 'update'])->name('tickets.update');
    Route::put('/tickets/{ticket}/assign', [Admin\TicketController::class, 'assign'])->name('tickets.assign');
    Route::get('/tickets/{ticket}/files/{attachment}', [Admin\TicketController::class, 'attachment'])->name('tickets.attachment');

    Route::get('/reconcile', [Admin\ReconcileController::class, 'index'])->name('reconcile.index');
    Route::get('/reconcile/export', [Admin\ReconcileController::class, 'export'])->name('reconcile.export');
    Route::post('/reconcile/run', [Admin\ReconcileController::class, 'run'])->name('reconcile.run')->middleware('throttle:6,1');
    Route::get('/reconcile/{issue}', [Admin\ReconcileController::class, 'show'])->name('reconcile.show');
    Route::post('/reconcile/{issue}/propose', [Admin\ReconcileController::class, 'propose'])->name('reconcile.propose');
    Route::post('/reconcile/{issue}/approve', [Admin\ReconcileController::class, 'approve'])->name('reconcile.approve');
    Route::post('/reconcile/{issue}/reject', [Admin\ReconcileController::class, 'reject'])->name('reconcile.reject');
    Route::post('/reconcile/{issue}/ignore', [Admin\ReconcileController::class, 'ignore'])->name('reconcile.ignore');

    Route::get('/staff', [Admin\StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [Admin\StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/permissions', [Admin\StaffController::class, 'permissions'])->name('staff.permissions');
    Route::put('/staff/threshold', [Admin\StaffController::class, 'threshold'])->name('staff.threshold');
    Route::put('/staff/{user}', [Admin\StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{user}', [Admin\StaffController::class, 'destroy'])->name('staff.destroy');

    Route::get('/wallet-approvals', [Admin\WalletApprovalController::class, 'index'])->name('wallet-approvals.index');
    Route::post('/wallet-approvals/{adjustment}/approve', [Admin\WalletApprovalController::class, 'approve'])->name('wallet-approvals.approve');
    Route::post('/wallet-approvals/{adjustment}/reject', [Admin\WalletApprovalController::class, 'reject'])->name('wallet-approvals.reject');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/wallet', [Admin\UserController::class, 'wallet'])->name('users.wallet');
    Route::get('/users/{user}/statement', [Admin\UserController::class, 'statementPdf'])->name('users.statement');
    Route::post('/users/{user}/statement', [Admin\UserController::class, 'statementEmail'])->name('users.statement.email')->middleware('throttle:10,1');
    Route::post('/users/{user}/reset-2fa', [Admin\UserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
    Route::post('/users/{user}/password-reset', [Admin\UserController::class, 'sendPasswordReset'])->name('users.password-reset')->middleware('throttle:6,1');

    Route::get('/groups', [Admin\GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [Admin\GroupController::class, 'store'])->name('groups.store');
    Route::put('/groups/{group}', [Admin\GroupController::class, 'update'])->name('groups.update');
    Route::post('/groups/{group}/default', [Admin\GroupController::class, 'makeDefault'])->name('groups.default');
    Route::delete('/groups/{group}', [Admin\GroupController::class, 'destroy'])->name('groups.destroy');
    Route::put('/groups-pricing', [Admin\GroupController::class, 'pricingSettings'])->name('groups.pricing-settings');
    Route::post('/groups-pricing/reprice', [Admin\GroupController::class, 'repriceAll'])->name('groups.reprice');
    Route::get('/pricing', [Admin\PricingController::class, 'index'])->name('pricing.index');
    Route::post('/pricing/requests/{priceChange}/approve', [Admin\PricingController::class, 'approve'])->name('pricing.approve');
    Route::post('/pricing/requests/{priceChange}/reject', [Admin\PricingController::class, 'reject'])->name('pricing.reject');
    Route::post('/pricing/sync', [Admin\PricingController::class, 'sync'])->name('pricing.sync')->middleware('throttle:4,1');

    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [Admin\ReportController::class, 'export'])->name('reports.export');
    Route::get('/activity', [Admin\ActivityLogController::class, 'index'])->name('activity.index');

    Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::resource('services', Admin\ServiceController::class)->except('show');
    Route::get('/popups', [Admin\PopupController::class, 'edit'])->name('popups.edit');
    Route::put('/popups', [Admin\PopupController::class, 'update'])->name('popups.update');
    Route::post('/popups/preview', [Admin\PopupController::class, 'preview'])->name('popups.preview');
    Route::get('/popups/source/{service}', [Admin\PopupController::class, 'source'])->name('popups.source');
    Route::post('/services/{service}/toggle', [Admin\ServiceController::class, 'toggle'])->name('services.toggle');

    Route::resource('suppliers', Admin\SupplierController::class)->except('show');
    Route::post('/suppliers/{supplier}/test', [Admin\SupplierController::class, 'test'])->name('suppliers.test');
    Route::get('/suppliers/{supplier}/services', [Admin\SupplierController::class, 'services'])->name('suppliers.services');
    Route::post('/suppliers/{supplier}/import', [Admin\SupplierController::class, 'import'])->name('suppliers.import');
    Route::post('/suppliers/{supplier}/sync-costs', [Admin\SupplierController::class, 'syncCosts'])->name('suppliers.sync-costs');

    Route::resource('posts', Admin\PostController::class)->except('show');
    Route::controller(Admin\ContentExtrasController::class)->group(function () {
        Route::get('/testimonials', 'testimonials')->name('testimonials.index');
        Route::post('/testimonials', 'storeTestimonial')->name('testimonials.store');
        Route::put('/testimonials/{testimonial}', 'updateTestimonial')->name('testimonials.update');
        Route::delete('/testimonials/{testimonial}', 'destroyTestimonial')->name('testimonials.destroy');

        Route::get('/downloads', 'downloads')->name('downloads.index');
        Route::post('/downloads', 'storeDownload')->name('downloads.store');
        Route::put('/downloads/{download}', 'updateDownload')->name('downloads.update');
        Route::delete('/downloads/{download}', 'destroyDownload')->name('downloads.destroy');

        Route::get('/announcements', 'announcements')->name('announcements.index');
        Route::post('/announcements', 'storeAnnouncement')->name('announcements.store');
        Route::put('/announcements/{announcement}', 'updateAnnouncement')->name('announcements.update');
        Route::delete('/announcements/{announcement}', 'destroyAnnouncement')->name('announcements.destroy');
    });

    Route::get('/settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/general', [Admin\SettingsController::class, 'updateGeneral'])->name('settings.general');
    Route::put('/settings/statements', [Admin\SettingsController::class, 'updateStatements'])->name('settings.statements');
    Route::put('/settings/social', [Admin\SettingsController::class, 'updateSocial'])->name('settings.social');
    Route::put('/settings/telegram',[Admin\SettingsController::class, 'updateTelegram'])->name('settings.telegram');
    Route::post('/settings/telegram/test', [Admin\SettingsController::class, 'testTelegram'])->name('settings.telegram.test')->middleware('throttle:6,1');

    Route::get('/points', [Admin\PointsController::class, 'index'])->name('points.index');
    Route::put('/points/rules', [Admin\PointsController::class, 'updateRules'])->name('points.rules');
    Route::post('/points/adjust', [Admin\PointsController::class, 'adjust'])->name('points.adjust');

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
