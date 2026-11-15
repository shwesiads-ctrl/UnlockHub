@extends('layouts.admin', ['title' => 'Card & PayPal top-ups', 'subtitle' => 'Credited automatically after the gateway confirms'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <a href="{{ route('admin.payments.index') }}" class="btn h-9 text-[13px]">← Receipt approvals</a>
    @foreach (['' => 'All', 'paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $k => $l)
        <a href="{{ route('admin.payments.online', array_filter(['status' => $k])) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => request('status', '') === $k])>{{ $l }}</a>
    @endforeach
    @can('settings.manage') <a href="{{ route('admin.settings.index') }}#online-payments" class="btn h-9 text-[13px] ml-auto">Gateway settings</a> @endcan
</div>
<section class="card table-wrap">
    <table class="table min-w-[860px]">
        <thead><tr><th>#</th><th>Date</th><th>Customer</th><th>Gateway</th><th class="text-right">Paid</th><th class="text-right">Credited</th><th>Status</th><th>Reference</th><th></th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr>
                <td class="text-muted">{{ $p->id }}</td>
                <td class="whitespace-nowrap text-muted">{{ $p->created_at->format('d M H:i') }}</td>
                <td>{{ $p->user?->username }}</td>
                <td>{{ \App\Models\OnlinePayment::GATEWAYS[$p->gateway] }}</td>
                <td class="text-right whitespace-nowrap">{{ number_format((float) $p->amount, 2) }} {{ $p->currency }}</td>
                <td class="text-right whitespace-nowrap font-semibold">{{ money($p->credit_ks) }}</td>
                <td><span @class(['badge', 'badge-success' => $p->status === 'paid', 'badge-pending' => $p->status === 'pending', 'badge-rejected' => in_array($p->status, ['failed']), 'bg-surface-2 text-muted' => in_array($p->status, ['cancelled', 'expired'])])>{{ ucfirst($p->status) }}</span>
                    @if ($p->error) <div class="text-xs text-danger">{{ $p->error }}</div> @endif</td>
                <td class="font-mono text-xs break-all">{{ $p->gateway_txn ?? $p->reference }}</td>
                <td>@if ($p->status === 'pending')<form method="POST" action="{{ route('admin.payments.online.sync', $p) }}">@csrf<button class="btn h-8 text-xs">Check now</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-10">No online payments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $payments->links() }}
@endsection
