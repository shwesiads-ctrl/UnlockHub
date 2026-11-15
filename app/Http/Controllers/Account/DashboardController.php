<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
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

        $pointsValue = $user->points * (float) setting('points.redeem_rate', 10);

        $favorites = $user->favorites()->visible()
            ->with(['prices' => fn ($p) => $p->where('user_group_id', $user->user_group_id ?? 0)])
            ->orderBy('services.name')->limit(8)->get();

        return view('account.dashboard', compact('user', 'orders', 'stats', 'pointsValue', 'favorites'));
    }
}
