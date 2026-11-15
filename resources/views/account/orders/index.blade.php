@extends('layouts.account', ['title' => 'Order History'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <nav aria-label="Status" class="flex flex-wrap gap-2">
        <a href="{{ route('account.orders.index') }}" @class(['btn h-9 text-[13px]', 'btn-primary' => ! $status && ! request('batch')])>All ({{ $counts->sum() }})</a>
        @foreach (\App\Models\Order::STATUS_LABELS as $k => $label)
            @if (($counts[$k] ?? 0) > 0 || $status === $k)
                <a href="{{ route('account.orders.index', ['status' => $k]) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => $status === $k])>{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
            @endif
        @endforeach
    </nav>
    <form method="GET" class="ml-auto flex gap-2">
        @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
        <label for="q" class="sr-only">Search</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Order no, IMEI, service…" class="input h-9 w-60 text-sm">
        <button class="btn h-9 text-[13px]">Search</button>
    </form>
</div>

@if (request('batch'))
    <div class="rounded-xl px-4 py-3 text-sm bg-brand-soft text-brand-ink flex flex-wrap gap-3 items-center">
        <span class="flex-1">Showing one bulk order ({{ $orders->total() }} items).</span>
        <a href="{{ route('account.orders.export', ['batch' => request('batch')]) }}" class="btn h-9 text-[13px]">Download CSV</a>
        <a href="{{ route('account.orders.index') }}" class="btn h-9 text-[13px]">Show all orders</a>
    </div>
@endif

<section class="card table-wrap">
    <table class="table min-w-[760px]">
        <thead><tr><th>Order</th><th>Service · Input</th><th>Result</th><th class="text-right">Price</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($orders as $order)
            <tr>
                <td class="whitespace-nowrap">
                    <a href="{{ route('account.orders.show', $order) }}" class="font-semibold">#{{ $order->order_no }}</a>
                    <div class="text-xs text-muted">{{ $order->created_at->format('d M H:i') }}</div>
                </td>
                <td><div class="font-medium">{{ $order->service_name }}</div><div class="text-xs text-muted font-mono">{{ $order->imei }}</div></td>
                <td class="font-mono text-[13px] max-w-xs">
                    @if ($order->status === 'success' && $order->result)
                        <span class="text-ink break-all">{{ \Illuminate\Support\Str::limit($order->result, 60) }}</span>
                    @elseif ($order->result)
                        <span class="text-muted">{{ \Illuminate\Support\Str::limit($order->result, 60) }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-right whitespace-nowrap">{{ price($order->price) }}</td>
                <td>
                    <x-status-badge :status="$order->status" />
                    @if ($order->refunded) <div class="text-[11px] text-muted mt-1">refunded</div> @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-10">No orders found. <a href="{{ route('services.index') }}">Browse services</a></td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<div class="flex flex-wrap gap-3 items-center justify-between">
    {{ $orders->links() }}
    @if ($orders->total() > 0 && ! request('batch'))
        <a href="{{ route('account.orders.export') }}" class="btn h-9 text-[13px]">Download CSV</a>
    @endif
</div>
@endsection
