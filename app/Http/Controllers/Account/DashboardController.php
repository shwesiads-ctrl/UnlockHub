<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('group');

        $orders = $user->orders()->latest()->limit(8)->get();

        $stats = [
            'open' => $user->orders()->whereIn('status', [Order::PENDING, Order::IN_PROCESS])->count(),
            'success_month' => $user->orders()->where('status', Order::SUCCESS)
                ->where('completed_at', '>=', now()->startOfMonth())->count(),
            'rejected_month' => $user->orders()->where('status', Order::REJECTED)
                ->where('updated_at', '>=', now()->startOfMonth())->count(),
        ];

        $pointsRules = PointService::rules();
        $pointsValue = $user->points * $pointsRules['redeem_rate'];
        $canRedeemPoints = PointService::on('redeem') && $pointsRules['redeem_rate'] > 0;
        $canConvertWallet = PointService::on('wallet_convert') && $pointsRules['convert_rate'] > 0;

        $favorites = $user->favorites()->visible()
            ->with(['prices' => fn ($p) => $p->where('user_group_id', $user->user_group_id ?? 0)])
            ->orderBy('services.name')->limit(8)->get();

        return view('account.dashboard', compact('user', 'orders', 'stats', 'pointsValue', 'favorites', 'canRedeemPoints', 'canConvertWallet'));
    }
}
