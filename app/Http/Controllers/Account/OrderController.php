<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Services\OrderService;
use App\Support\OrderInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->status === 'active', 404);

        $request->validate([
            'fields' => ['array'],
            'user_note' => ['nullable', 'string', 'max:255'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Please confirm you checked the details.']);

        $rows = OrderInput::rows($service, (array) $request->input('fields', []));

        try {
            $placed = $this->orders->place($request->user(), $service, $rows, $request->input('user_note'));
        } catch (InsufficientBalanceException $e) {
            return back()->withInput()->with('error', $e->getMessage().' Add funds and try again.');
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $first = $placed->first();
        $message = $placed->count() > 1
            ? "{$placed->count()} orders placed. Total ".money($placed->sum('price')).'.'
            : "Order #{$first->order_no} placed.";

        return $placed->count() > 1
            ? redirect()->route('account.orders.index', ['batch' => $first->batch_id])->with('success', $message)
            : redirect()->route('account.orders.show', $first)->with('success', $message);
    }

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), Order::STATUS_LABELS) ? $request->query('status') : null;

        $orders = $request->user()->orders()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('batch'), fn ($q, $b) => $q->where('batch_id', $b))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('order_no', 'like', "%{$s}%")
                ->orWhere('imei', 'like', "%{$s}%")
                ->orWhere('service_name', 'like', "%{$s}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $counts = $request->user()->orders()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('account.orders.index', compact('orders', 'status', 'counts'));
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $order->load('service.fields');

        return view('account.orders.show', compact('order'));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        try {
            $this->orders->cancelByUser($order);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order #{$order->order_no} cancelled and ".money($order->price).' refunded.');
    }

    /** Download a batch (or filtered list) as CSV: order, input, status, result. */
    public function export(Request $request): StreamedResponse
    {
        $query = $request->user()->orders()
            ->when($request->query('batch'), fn ($q, $b) => $q->where('batch_id', $b))
            ->latest('id')->limit(5000);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Date', 'Service', 'Input', 'Price', 'Status', 'Result']);
            foreach ($query->cursor() as $o) {
                fputcsv($out, [
                    $o->order_no, $o->created_at->format('Y-m-d H:i'), $o->service_name,
                    $o->imei, (float) $o->price, $o->statusLabel(), $o->result,
                ]);
            }
            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
