<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.numbered-pagination');
        \App\Support\MailSettings::apply(); // SMTP saved in Admin › Settings

        // @can('orders.process') etc. → staff permissions
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            return array_key_exists($ability, \App\Support\Permissions::ALL) ? $user->canDo($ability) : null;
        });
        Model::preventLazyLoading(! $this->app->isProduction());

        // Short names in polymorphic columns (wallet/point/activity references)
        Relation::enforceMorphMap([
            'user' => User::class,
            'order' => Order::class,
            'payment_request' => PaymentRequest::class,
            'wallet_adjustment' => \App\Models\WalletAdjustment::class,
            'wallet_transaction' => \App\Models\WalletTransaction::class,
            'withdrawal_request' => \App\Models\WithdrawalRequest::class,
            'online_payment' => \App\Models\OnlinePayment::class,
            'reconciliation_issue' => \App\Models\ReconciliationIssue::class,
            'point_transaction' => \App\Models\PointTransaction::class,
            'service' => \App\Models\Service::class,
            'supplier' => \App\Models\Supplier::class,
            // Every other model that can be an activity-log subject or a reference
            'ticket' => \App\Models\Ticket::class,
            'ticket_message' => \App\Models\TicketMessage::class,
            'reseller_profile' => \App\Models\ResellerProfile::class,
            'reseller_price' => \App\Models\ResellerPrice::class,
            'payment_method' => \App\Models\PaymentMethod::class,
            'price_change_request' => \App\Models\PriceChangeRequest::class,
            'service_category' => \App\Models\ServiceCategory::class,
            'service_price' => \App\Models\ServicePrice::class,
            'user_group' => \App\Models\UserGroup::class,
            'post' => \App\Models\Post::class,
            'download' => \App\Models\Download::class,
            'testimonial' => \App\Models\Testimonial::class,
            'announcement' => \App\Models\Announcement::class,
            'canned_reply' => \App\Models\CannedReply::class,
            'reconciliation_run' => \App\Models\ReconciliationRun::class,
            'order_request' => \App\Models\OrderRequest::class,
            'social_account' => \App\Models\SocialAccount::class,
            'trusted_device' => \App\Models\TrustedDevice::class,
        ]);

        View::composer('layouts.app', function ($view) {
            $view->with('announcement', rescue(fn () => Announcement::current()->latest()->first(), null, false));
        });

        View::composer('layouts.admin', function ($view) {
            $view->with('adminBadges', rescue(fn () => [
                'manual' => Order::manualQueue()->count(),
                'payments' => PaymentRequest::where('status', PaymentRequest::PENDING)->count(),
                'approvals' => \App\Models\WalletAdjustment::where('status', 'pending')->count(),
                'pricing' => \App\Models\PriceChangeRequest::where('status', 'pending')->count() + \App\Models\Service::where('sales_paused', true)->count(),
                'resellers' => \App\Models\ResellerProfile::where('status', 'pending')->count(),
                'withdrawals' => \App\Models\WithdrawalRequest::where('status', 'pending')->count(),
                'reconcile' => \App\Models\ReconciliationIssue::whereIn('status', ['open', 'proposed'])->count(),
                'tickets' => \App\Models\Ticket::where('awaiting_staff', true)->whereNotIn('status', ['resolved', 'closed'])->count(),
            ], ['manual' => 0, 'payments' => 0, 'approvals' => 0, 'tickets' => 0], false));
        });
    }
}
