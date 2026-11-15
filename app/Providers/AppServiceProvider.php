<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Model::preventLazyLoading(! $this->app->isProduction());

        // Short names in polymorphic columns (wallet/point/activity references)
        Relation::enforceMorphMap([
            'user' => User::class,
            'order' => Order::class,
            'payment_request' => PaymentRequest::class,
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
            ], ['manual' => 0, 'payments' => 0], false));
        });
    }
}
