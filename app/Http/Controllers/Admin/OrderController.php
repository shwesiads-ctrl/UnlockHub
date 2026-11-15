<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class OrderController extends Controller
{
    public const TABS = [
        'all' => 'All',
        'manual' => 'Manual queue',
        'api' => 'API',
        'api_failed' => 'API failed',
        'success' => 'Success',
        'rejected' => 'Rejected',
    ];

    public function __construct(private OrderService $orders) {}

    public function index(Request $request, ?string $forcedTab = null): View
    {
        $tab = $forcedTab ?? (array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'all');

        $query = Order::query()->with(['user:id,username', 'supplier:id,name']);
        $this->applyTab($query, $tab);

        $orders = $query
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('order_no', 'like', "%{$s}%")
                ->orWhere('imei', 'like', "%{$s}%")
                ->orWhere('supplier_order_id', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$s}%"))))
            ->when($request->integer('service'), fn ($q, $id) => $q->where('service_id', $id))
            ->when($request->query('batch'), fn ($q, $b) => $q->where('batch_id', $b))
            // Manual queue: oldest first, so nothing waits too long
            ->orderBy('id', $tab === 'manual' ? 'asc' : 'desc')
            ->paginate(40)->withQueryString();

        $counts = [];
        foreach (array_keys(self::TABS) as $t) {
            $q = Order::query();
            $this->applyTab($q, $t);
            $counts[$t] = $q->count();
        }

        return view('admin.orders.index', compact('orders', 'tab', 'counts'));
    }

    public function manual(Request $request): View
    {
        return $this->index($request, 'manual');
    }

    public function show(Order $order): View
    {
        $order->load(['user.group', 'service.fields', 'supplier', 'processor:id,name']);

        $batchCount = $order->batch_id ? Order::where('batch_id', $order->batch_id)->count() : 0;

        $nextManual = Order::manualQueue()->whereKeyNot($order->id)->orderBy('id')->first();

        return view('admin.orders.show', compact('order', 'batchCount', 'nextManual'));
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:in_process,success,reject,retry_api,note'],
            'result' => ['nullable', 'required_if:action,success', 'required_if:action,reject', 'string', 'max:5000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'result.required_if' => 'Enter the result (code) or the reason the user will see.',
        ]);

        try {
            switch ($data['action']) {
                case 'in_process':
                    $this->orders->markInProcess($order);
                    $msg = 'Marked In Process.';
                    break;
                case 'success':
                    $this->orders->complete($order, $data['result'], $data['admin_note'] ?? null);
                    $msg = "Order #{$order->order_no} completed".($order->points ? " · +{$order->points} points to user" : '').'.';
                    break;
                case 'reject':
                    $this->orders->reject($order, $data['result'], $data['admin_note'] ?? null);
                    $msg = "Order #{$order->order_no} rejected · ".money($order->price).' refunded.';
                    break;
                case 'retry_api':
                    $this->orders->retryApi($order);
                    $msg = 'Sent to the supplier again.';
                    break;
                default:
                    $order->forceFill(['admin_note' => $data['admin_note'] ?? null])->save();
                    $msg = 'Note saved.';
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // After finishing a manual order, jump to the next one in the queue
        if (in_array($data['action'], ['success', 'reject'], true) && $request->boolean('go_next')) {
            $next = Order::manualQueue()->orderBy('id')->first();
            if ($next) {
                return redirect()->route('admin.orders.show', $next)->with('success', $msg.' Next order in the queue opened.');
            }

            return redirect()->route('admin.orders.manual')->with('success', $msg.' Manual queue is empty.');
        }

        return back()->with('success', $msg);
    }

    /** Bulk: mark many pending manual orders In Process at once. */
    public function bulkInProcess(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']])['ids'];

        $n = DB::transaction(fn () => Order::whereIn('id', $ids)
            ->where('status', Order::PENDING)->where('processing_mode', 'manual')
            ->update(['status' => Order::IN_PROCESS, 'processed_by' => $request->user()->id, 'updated_at' => now()]));

        return back()->with('success', "{$n} orders marked In Process.");
    }

    private function applyTab($query, string $tab): void
    {
        match ($tab) {
            'manual' => $query->manualQueue(),
            'api' => $query->where('processing_mode', 'api'),
            'api_failed' => $query->where('api_failed', true)->whereIn('status', [Order::PENDING, Order::IN_PROCESS]),
            'success' => $query->where('status', Order::SUCCESS),
            'rejected' => $query->whereIn('status', [Order::REJECTED, Order::CANCELLED]),
            default => null,
        };
    }
}
