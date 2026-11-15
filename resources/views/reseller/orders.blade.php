@extends('layouts.account', ['title' => __('Customer orders')])

@section('content')
@include('reseller._nav')
<form method="GET" class="card p-3 flex flex-wrap gap-2">
    <label for="q" class="sr-only">{{ __('Search') }}</label><input id="q" name="q" value="{{ request('q') }}" class="input h-10 flex-1 min-w-48" placeholder="{{ __('Order no. or IMEI') }}">
    <label for="status" class="sr-only">{{ __('Status') }}</label>
    <select id="status" name="status" class="input h-10 w-auto"><option value="">{{ __('All') }}</option>@foreach (\App\Models\Order::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select>
    <button class="btn h-10">{{ __('Filter') }}</button>
</form>
<section class="card table-wrap">
    <table class="table min-w-[760px]">
        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Service') }}</th><th class="text-right">{{ __('Customer paid') }}</th><th class="text-right">{{ __('Your cost') }}</th><th class="text-right">{{ __('Profit') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                <td class="font-mono text-[13px]">#{{ $o->order_no }}<div class="text-xs text-muted">{{ $o->created_at->format('d M H:i') }}</div></td>
                <td>{{ $o->user?->username }}</td>
                <td>{{ \Illuminate\Support\Str::limit($o->service_name, 40) }}<div class="text-xs text-muted font-mono">{{ $o->imei }}</div></td>
                <td class="text-right">{{ money($o->price) }}</td>
                <td class="text-right text-muted">{{ money($o->reseller_price) }}</td>
                <td @class(['text-right font-semibold', 'text-ok' => $o->status === 'success', 'text-muted' => $o->status !== 'success'])>{{ money((float) $o->price - (float) $o->reseller_price) }}</td>
                <td><x-status-badge :status="$o->status" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-8">{{ __('No orders.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $orders->links() }}
<p class="m-0 text-xs text-muted">{{ __('Profit is added to your wallet when an order succeeds. Rejected orders are refunded to the customer.') }}</p>
@endsection
