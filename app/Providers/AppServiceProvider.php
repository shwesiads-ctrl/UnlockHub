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
            'point_transaction' => \App\Models\PointTransaction::class,
            'service' => \App\Models\Service::class,
            'supplier' => \App\Models\Supplier::class,
        ]);

        View::composer('layouts.app', function ($view) {
            $view->with('announcement', rescue(fn () => Announcement::current()->latest()->first(), null, false));
        });

        View::composer('layouts.admin', function ($view) {
            $view->with('adminBadges', rescue(fn () => [
                'manual' => Order::manualQueue()->count(),
                'payments' => PaymentRequest::where('status', PaymentRequest::PENDING)->count(),
                'approvals' => \App\Models\WalletAdjustment::where('status', 'pending')->count(),
                'tickets' => \Illuminate\Support\Facades\Schema::hasTable('tickets') ? \App\Models\Ticket::whereIn('status', ['open', 'customer_replied'])->count() : 0,
            ], ['manual' => 0, 'payments' => 0, 'approvals' => 0, 'tickets' => 0], false));
        });
    }
}
