<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = Order::whereDate('created_at', today());
        $doneToday = Order::where('status', Order::SUCCESS)->whereDate('completed_at', today());

        $stats = [
            'sales_today' => (float) (clone $doneToday)->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(reseller_price, price)')),
            'profit_today' => (float) (clone $doneToday)->selectRaw('COALESCE(SUM(COALESCE(reseller_price, price) - cost), 0) AS p')->value('p'),
            'orders_today' => (clone $today)->count(),
            'api_today' => (clone $today)->where('processing_mode', 'api')->count(),
            'open' => Order::whereIn('status', [Order::PENDING, Order::IN_PROCESS])->count(),
            'api_failed' => Order::where('api_failed', true)->whereIn('status', [Order::PENDING, Order::IN_PROCESS])->count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'wallets' => (float) User::sum('balance'),
        ];

        // Last 7 days of completed sales
        $from = now()->subDays(6)->startOfDay();
        $sales = Order::where('status', Order::SUCCESS)->where('completed_at', '>=', $from)
            ->selectRaw('DATE(completed_at) AS d, SUM(COALESCE(reseller_price, price)) AS s, SUM(COALESCE(reseller_price, price) - cost) AS p')
            ->groupBy('d')->get()->keyBy('d');

        $week = [];
        foreach (CarbonPeriod::create($from, '1 day', now()->startOfDay()) as $day) {
            $row = $sales->get($day->toDateString());
            $week[] = ['label' => $day->format('D'), 'date' => $day->toDateString(), 'sales' => (float) ($row->s ?? 0), 'profit' => (float) ($row->p ?? 0)];
        }

        return view('admin.dashboard', [
            'stats' => $stats,
            'week' => $week,
            'pendingPayments' => PaymentRequest::with(['user:id,username', 'method:id,name'])->where('status', PaymentRequest::PENDING)->orderBy('id')->limit(5)->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'status', 'balance', 'currency', 'last_error', 'last_synced_at']),
            'topServices' => Order::where('status', Order::SUCCESS)->where('completed_at', '>=', now()->subDays(7))
                ->selectRaw('service_name, COUNT(*) AS n, SUM(COALESCE(reseller_price, price)) AS sales')
                ->groupBy('service_name')->orderByDesc('n')->limit(5)->get(),
            'activity' => ActivityLog::with('user:id,username')->latest('id')->limit(8)->get(),
        ]);
    }
}
