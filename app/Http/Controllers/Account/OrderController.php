<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\DuplicateOrderException;
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
            'request_token' => ['nullable', 'string', 'max:64'],
        ], ['confirm.accepted' => 'Please confirm you checked the details.']);

        $fields = (array) $request->input('fields', []);
        $rows = OrderInput::rows($service, $fields);
        $repeated = OrderInput::repeatedLines($service, $fields);

        try {
            $placed = $this->orders->place($request->user(), $service, $rows, $request->input('user_note'), [
                // Same token again (double click, refresh, resend after lost connection) → same orders, charged once
                'key' => $request->filled('request_token') ? 'web:'.$request->input('request_token') : null,
                'source' => 'web',
                'allow_duplicates' => $request->boolean('confirm_duplicates'),
            ]);
        } catch (DuplicateOrderException $e) {
            return back()->withInput($request->except('confirm_duplicates'))
                ->with('duplicates', $e->existing->map(fn ($o) => [
                    'order_no' => $o->order_no, 'imei' => $o->imei, 'status' => $o->statusLabel(),
                    'url' => route('account.orders.show', $o), 'date' => $o->created_at->format('d M H:i'),
                ])->all())
                ->with('duplicates_blocked', $e->blocked)
                ->with('error', $e->getMessage());
        } catch (InsufficientBalanceException $e) {
            return back()->withInput()->with('error', $e->getMessage().' Add funds and try again.');
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $first = $placed->first();
        $message = $placed->count() > 1
            ? "{$placed->count()} orders placed. Total ".unit_price($placed->sum('price'), $first->price_currency ?? 'ks').'.'
            : "Order #{$first->order_no} placed.";
        if ($repeated) {
            $message .= ' '.count($repeated).' repeated line(s) were ordered only once: '.\Illuminate\Support\Str::limit(implode(', ', $repeated), 120);
        }

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

    /**
     * "Order again": open the service form pre-filled with this order's details (a bulk
     * order brings back all its IMEIs). Nothing is placed or charged until the customer
     * checks the details and submits.
     */
    public function reorder(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $service = $order->service()->with('fields')->first();

        if (! $service || $service->status !== 'active') {
            return back()->with('error', 'This service is no longer available. Please choose another one.');
        }

        $orders = $order->batch_id
            ? $request->user()->orders()->where('batch_id', $order->batch_id)->orderBy('id')->get(['input', 'price', 'user_note'])
            : collect([$order]);

        $fields = [];
        $bulk = $service->allow_bulk ? $service->fields->firstWhere('is_bulk_field', true) : null;
        foreach ($service->fields as $f) {
            $values = $orders->map(fn ($o) => (string) (((array) $o->input)[$f->name] ?? ''))->filter(fn ($v) => $v !== '');
            $fields[$f->name] = $bulk && $f->is($bulk) ? $values->unique()->implode("\n") : (string) $values->first();
        }

        return redirect()->route('services.show', $service)
            ->withInput(['fields' => $fields, 'user_note' => $order->user_note])
            ->with('reorder', [
                'order_no' => $order->order_no,
                'old_price' => (float) $order->price,
                'old_price_currency' => $order->price_currency ?? 'ks',
                'items' => $orders->count(),
            ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        try {
            $this->orders->cancelByUser($order);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order #{$order->order_no} cancelled and ".$order->priceLabel().(($order->price_currency ?? 'ks') === 'points' ? ' returned.' : ' refunded.'));
    }

    /** Download a batch (or filtered list) as CSV: order, input, status, result. */
    public function export(Request $request): StreamedResponse
    {
        $query = $request->user()->orders()
            ->when($request->query('batch'), fn ($q, $b) => $q->where('batch_id', $b))
            ->latest('id')->limit(5000);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Date', 'Service', 'Input', 'Price', 'Currency', 'Status', 'Result']);
            foreach ($query->cursor() as $o) {
                fputcsv($out, \App\Support\Spreadsheet::safeRow([
                    $o->order_no, $o->created_at->format('Y-m-d H:i'), $o->service_name,
                    $o->imei, (float) $o->price, $o->price_currency ?? 'ks', $o->statusLabel(), $o->result,
                ]));
            }
            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
