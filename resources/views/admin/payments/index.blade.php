@extends('layouts.admin', ['title' => 'Payment Approvals', 'subtitle' => 'Check each receipt against your payment app before approving.'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <nav aria-label="Status" class="flex flex-wrap gap-2">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label)
            @php $n = $k === 'all' ? $counts->sum() : ($counts[$k] ?? 0); @endphp
            <a href="{{ route('admin.payments.index', ['status' => $k]) }}" @class(['btn h-10', 'btn-primary' => $status === $k])>{{ $label }} ({{ $n }})</a>
        @endforeach
        <a href="{{ route('admin.payments.online') }}" class="btn h-10">Card &amp; PayPal</a>
    </nav>
    <form method="GET" class="ml-auto flex gap-2">
        <input type="hidden" name="status" value="{{ $status }}">
        <label for="q" class="sr-only">Search</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Txn ID, username, email…" class="input h-10 w-64">
        <button class="btn h-10">Search</button>
    </form>
    <a href="{{ route('admin.payment-methods.index') }}" class="btn h-10">Payment methods</a>
</div>

<section class="card table-wrap">
    <table class="table min-w-[820px]">
        <thead><tr><th>User</th><th>Method</th><th>Txn ID</th><th class="text-right">Amount</th><th>Submitted</th><th>Status</th><th><span class="sr-only">Open</span></th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td><div class="font-semibold">{{ $r->user->username }}</div><div class="text-xs text-muted">{{ $r->user->name }}</div></td>
                <td>{{ $r->method->name }}</td>
                <td class="font-mono text-[13px]">
                    {{ $r->transaction_id }}
                    @if (isset($dupes[$r->payment_method_id.'|'.$r->transaction_id]))
                        <div class="font-sans text-[11px] font-bold text-danger">DUPLICATE TXN ID</div>
                    @endif
                </td>
                <td class="text-right font-display font-bold whitespace-nowrap">
                    {{ money($r->amount) }}
                    @if ($r->status === 'approved' && (float) $r->credited_amount !== (float) $r->amount)
                        <div class="font-sans text-xs text-muted font-normal">credited {{ money($r->credited_amount) }}</div>
                    @endif
                </td>
                <td class="whitespace-nowrap text-muted">{{ $r->created_at->format('d M H:i') }}<div class="text-xs">{{ $r->created_at->diffForHumans() }}</div></td>
                <td>
                    <x-status-badge :status="$r->status" />
                    @if ($r->reviewer) <div class="text-xs text-muted mt-1">by {{ $r->reviewer->name }}</div> @endif
                </td>
                <td class="text-right"><a href="{{ route('admin.payments.show', $r) }}" class="btn h-9 text-xs {{ $r->status === 'pending' ? 'btn-primary' : '' }}">{{ $r->status === 'pending' ? 'Review' : 'View' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-10">{{ $status === 'pending' ? 'All caught up — no receipts waiting.' : 'Nothing here.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

{{ $requests->links() }}
@endsection
