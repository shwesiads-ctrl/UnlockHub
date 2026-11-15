<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PointService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('group');

        $orders = $user->orders()->latest()->limit(8)->get();

        $monthStart = now()->startOfMonth();
        $monthOrders = $user->orders()->where('created_at', '>=', $monthStart);
        $pointRate = max(1, (int) setting('points.convert_rate', 10));
        $spentExpr = "CASE WHEN price_currency = 'points' THEN price * COALESCE(price_rate, {$pointRate}) ELSE price END";

        $stats = [
            'open' => $user->orders()->whereIn('status', [Order::PENDING, Order::IN_PROCESS])->count(),
            'success_month' => $user->orders()->where('status', Order::SUCCESS)
                ->where('completed_at', '>=', $monthStart)->count(),
            'rejected_month' => $user->orders()->where('status', Order::REJECTED)
                ->where('updated_at', '>=', $monthStart)->count(),
            'month_orders' => (clone $monthOrders)->count(),
            'month_spent' => (float) $user->orders()->where('status', Order::SUCCESS)
                ->where('completed_at', '>=', $monthStart)->sum(DB::raw($spentExpr)),
        ];
        $finalizedMonth = $stats['success_month'] + $stats['rejected_month'];
        $stats['success_rate'] = $finalizedMonth > 0
            ? (int) round($stats['success_month'] / $finalizedMonth * 100)
            : 0;

        $from = now()->subDays(6)->startOfDay();
        $dailyCounts = $user->orders()->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupBy('day')->get()->keyBy('day');
        $week = [];
        foreach (CarbonPeriod::create($from, '1 day', now()->startOfDay()) as $day) {
            $row = $dailyCounts->get($day->toDateString());
            $week[] = ['label' => $day->format('D'), 'date' => $day->toDateString(), 'orders' => (int) ($row?->total ?? 0)];
        }

        $pointsRules = PointService::rules();
        $pointsValue = $user->points * $pointsRules['redeem_rate'];
        $canRedeemPoints = PointService::on('redeem') && $pointsRules['redeem_rate'] > 0;
        $canConvertWallet = PointService::on('wallet_convert') && $pointsRules['convert_rate'] > 0;

        $favorites = $user->favorites()->visible()
            ->with(['prices' => fn ($p) => $p->where('user_group_id', $user->user_group_id ?? 0)])
            ->orderBy('services.name')->limit(8)->get();

        return view('account.dashboard', compact('user', 'orders', 'stats', 'week', 'pointsValue', 'favorites', 'canRedeemPoints', 'canConvertWallet'));
    }
}
