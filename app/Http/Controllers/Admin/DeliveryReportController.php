<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ServiceDeliveryStat;
use App\Services\DeliveryStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryReportController extends Controller
{
    public function index(Request $request): View
    {
        $days = (int) $request->query('days') === 7 ? 7 : 30;

        $stats = ServiceDeliveryStat::with(['service:id,name,slug,delivery_time,max_delivery_minutes', 'supplier:id,name'])
            ->where('window_days', $days)
            ->orderBy('service_id')->orderBy('supplier_key')
            ->get()
            ->groupBy('service_id');

        $overdue = Order::query()
            ->join('services', 'services.id', '=', 'orders.service_id')
            ->whereIn('orders.status', [Order::PENDING, Order::IN_PROCESS])
            ->whereNotNull('services.max_delivery_minutes')
            ->whereRaw('orders.created_at < ?', [now()->subMinutes(1)]) // filtered precisely below
            ->orderBy('orders.created_at')
            ->limit(500)
            ->get(['orders.id', 'orders.order_no', 'orders.service_name', 'orders.status', 'orders.created_at', 'services.max_delivery_minutes'])
            ->filter(fn ($o) => $o->created_at->lt(now()->subMinutes((int) $o->max_delivery_minutes)))
            ->take(100);

        return view('admin.reports.delivery', [
            'days' => $days,
            'stats' => $stats,
            'overdue' => $overdue,
            'computedAt' => ServiceDeliveryStat::max('computed_at'),
            'minSamples' => ServiceDeliveryStat::minSamples(),
        ]);
    }

    public function refresh(DeliveryStatsService $service): RedirectResponse
    {
        $service->compute();

        return back()->with('success', 'Delivery times recalculated.');
    }
}
