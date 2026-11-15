@extends('layouts.admin', [
    'title' => $tab === 'manual' ? 'Manual Queue' : 'Orders',
    'subtitle' => $tab === 'manual' ? 'Oldest first. Enter the result, then mark Success or Reject & refund.' : 'API orders update automatically · failed API orders drop into the Manual queue.',
])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <nav aria-label="Order view" class="flex flex-wrap gap-2">
        @foreach (\App\Http\Controllers\Admin\OrderController::TABS as $k => $label)
            <a href="{{ $k === 'manual' ? route('admin.orders.manual') : route('admin.orders.index', ['tab' => $k]) }}"
               @class(['btn h-10', 'btn-primary' => $tab === $k])>{{ $label }} ({{ $counts[$k] }})</a>
        @endforeach
    </nav>
    <form method="GET" class="ml-auto flex gap-2">
        @if ($tab !== 'manual') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
        <label for="q" class="sr-only">Search</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Order no, IMEI, user, supplier ref…" class="input h-10 w-72">
        <button class="btn h-10">Search</button>
    </form>
</div>

<form method="POST" action="{{ route('admin.orders.bulk-in-process') }}" id="bulk-form">
    @csrf
</form>

<section class="card table-wrap">
    <table class="table min-w-[900px]">
        <thead>
            <tr>
                @if ($tab === 'manual') <th class="w-10"><span class="sr-only">Select</span></th> @endif
                <th>Order</th><th>Service · Input</th><th>Mode</th><th class="text-right">Price</th><th>Status</th><th><span class="sr-only">Open</span></th>
            </tr>
        </thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                @if ($tab === 'manual')
                    <td>
                        @if ($o->status === 'pending')
                            <label class="sr-only" for="sel{{ $o->id }}">Select #{{ $o->order_no }}</label>
                            <input id="sel{{ $o->id }}" type="checkbox" name="ids[]" value="{{ $o->id }}" form="bulk-form" class="w-5 h-5 accent-[var(--uh-brand)]">
                        @endif
                    </td>
                @endif
                <td class="whitespace-nowrap">
                    <a href="{{ route('admin.orders.show', $o) }}" class="font-bold">#{{ $o->order_no }}</a>
                    <div class="text-xs text-muted">{{ $o->user->username }} · {{ $o->created_at->format('d M H:i') }}</div>
                    @if ($o->isOpen()) <div class="text-[11px] text-muted">waiting {{ $o->created_at->diffForHumans(null, true) }}</div> @endif
                </td>
                <td><div class="font-medium">{{ $o->service_name }}</div><div class="text-xs text-muted font-mono">{{ $o->imei }}</div></td>
                <td>
                    <x-status-badge :status="$o->processing_mode" />
                    @if ($o->api_failed) <div class="text-[11px] font-bold text-danger mt-1">API failed → manual</div> @endif
                    @if ($o->supplier) <div class="text-[11px] text-muted mt-1">{{ $o->supplier->name }}</div> @endif
                </td>
                <td class="text-right whitespace-nowrap">{{ money($o->price) }}</td>
                <td><x-status-badge :status="$o->status" /></td>
                <td class="text-right"><a href="{{ route('admin.orders.show', $o) }}" @class(['btn h-9 text-xs', 'btn-primary' => $o->isOpen() && $o->processing_mode === 'manual'])>{{ $o->isOpen() && $o->processing_mode === 'manual' ? 'Process' : 'View' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-10">{{ $tab === 'manual' ? 'Manual queue is empty.' : 'No orders.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<div class="flex flex-wrap gap-3 items-center justify-between">
    {{ $orders->links() }}
    @if ($tab === 'manual' && $orders->isNotEmpty())
        <button form="bulk-form" class="btn h-10">Mark selected In Process</button>
    @endif
</div>
@endsection
