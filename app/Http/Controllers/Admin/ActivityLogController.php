<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user:id,username')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->query('user'), fn ($q, $u) => $q->whereHas('user', fn ($w) => $w->where('username', $u)))
            ->latest('id')
            ->paginate(50)->withQueryString();

        $actions = ActivityLog::selectRaw("DISTINCT SUBSTR(action, 1, INSTR(action, '.') - 1) AS prefix")
            ->pluck('prefix')->filter()->sort()->values();

        // order_no for links (orders are routed by order number)
        $orderNos = \App\Models\Order::whereIn('id', $logs->where('subject_type', 'order')->pluck('subject_id'))->pluck('order_no', 'id');

        return view('admin.activity.index', compact('logs', 'actions', 'orderNos'));
    }
}
