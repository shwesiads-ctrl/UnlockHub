@extends('layouts.admin', ['title' => 'Wallet reconciliation', 'subtitle' => 'Balances vs. transaction ledger — reports only, never auto-corrects'])

@section('content')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="card p-5 flex flex-col gap-1">
        <span class="text-[13px] font-semibold text-muted">Last check</span>
        <span class="font-display text-xl font-bold">{{ $lastRun?->finished_at?->diffForHumans() ?? 'Never' }}</span>
        @if ($lastRun) <span class="text-xs text-muted">{{ number_format($lastRun->users_checked) }} users · {{ number_format($lastRun->transactions_checked) }} transactions · {{ $lastRun->trigger?->username ?? 'scheduler' }}</span> @endif
    </div>
    <div class="card p-5 flex flex-col gap-1">
        <span class="text-[13px] font-semibold text-muted">Open issues</span>
        <span @class(['font-display text-2xl font-bold', 'text-danger' => $counts->sum() > 0, 'text-ok' => $counts->sum() === 0])>{{ $counts->sum() }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-1">
        <span class="text-[13px] font-semibold text-muted">All wallet balances</span>
        <span class="font-display text-xl font-bold">{{ money($totals['balances']) }}</span>
        <span @class(['text-xs', 'text-ok' => abs($totals['balances'] - $totals['ledger']) < 0.01, 'text-danger' => abs($totals['balances'] - $totals['ledger']) >= 0.01])>Ledger total {{ money($totals['ledger']) }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2 justify-center">
        @can('reconcile.approve')
            <form method="POST" action="{{ route('admin.reconcile.run') }}" data-submit-once>@csrf<button class="btn btn-primary w-full">Run check now</button></form>
        @endcan
        <a href="{{ route('admin.reconcile.export', ['from' => $from, 'to' => $to]) }}" class="btn w-full">Export report (CSV)</a>
        <span class="text-xs text-muted text-center">Runs daily at 02:30 automatically.</span>
    </div>
</div>

<section class="card overflow-hidden">
    <form method="GET" class="px-5 py-4 flex flex-wrap gap-3 items-end">
        <h2 class="m-0 text-lg font-bold mr-auto">Money in / out by type</h2>
        <input type="hidden" name="status" value="{{ $status }}">
        <div><label for="from" class="label text-xs">From</label><input id="from" name="from" type="date" value="{{ $from }}" class="input h-9"></div>
        <div><label for="to" class="label text-xs">To</label><input id="to" name="to" type="date" value="{{ $to }}" class="input h-9"></div>
        <button class="btn h-9 text-sm">Show</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Type</th><th class="text-right">Transactions</th><th class="text-right">Total</th></tr></thead>
            <tbody>
            @foreach (['deposit' => 'Deposits (top-ups)', 'order' => 'Order debits', 'refund' => 'Refunds', 'points_redeem' => 'Point redemptions', 'admin_credit' => 'Admin adjustments (+)', 'admin_debit' => 'Admin adjustments (−)'] as $k => $label)
                @php $row = $breakdown[$k] ?? null; @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td class="text-right">{{ number_format($row->n ?? 0) }}</td>
                    <td @class(['text-right font-semibold', 'text-ok' => ($row->total ?? 0) > 0, 'text-danger' => ($row->total ?? 0) < 0])>{{ money($row->total ?? 0) }}</td>
                </tr>
            @endforeach
            <tr class="font-bold"><td>Net</td><td class="text-right">{{ number_format($breakdown->sum('n')) }}</td><td class="text-right">{{ money($breakdown->sum('total')) }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

<div class="flex flex-wrap gap-2 items-center">
    @foreach (['open' => 'Open', 'corrected' => 'Corrected', 'ignored' => 'Checked / ignored', 'cleared' => 'Cleared by itself', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.reconcile.index', array_merge(request()->except('page'), ['status' => $k])) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => $status === $k])>{{ $l }}</a>
    @endforeach
    <form method="GET" class="ml-auto flex gap-2">
        <input type="hidden" name="status" value="{{ $status }}">
        <label for="type" class="sr-only">Type</label>
        <select id="type" name="type" class="input h-9 w-auto text-sm" onchange="this.form.submit()">
            <option value="">All types</option>
            @foreach (\App\Models\ReconciliationIssue::TYPES as $k => $l) <option value="{{ $k }}" @selected($type === $k)>{{ $l }}{{ isset($counts[$k]) ? ' ('.$counts[$k].')' : '' }}</option> @endforeach
        </select>
    </form>
</div>

<section class="card table-wrap">
    <table class="table min-w-[860px]">
        <thead><tr><th>#</th><th>Problem</th><th>Customer</th><th class="text-right">Expected</th><th class="text-right">Actual</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($issues as $i)
            <tr>
                <td class="text-muted">{{ $i->id }}</td>
                <td>
                    <span @class(['badge', 'badge-rejected' => $i->severity === 'high', 'badge-pending' => $i->severity === 'medium', 'bg-surface-2 text-muted' => $i->severity === 'low'])>{{ ucfirst($i->severity) }}</span>
                    <span class="font-semibold">{{ $i->typeLabel() }}</span>
                    <div class="text-xs text-muted">{{ $i->message }}</div>
                </td>
                <td>{{ $i->user?->username ?? '—' }}</td>
                <td class="text-right whitespace-nowrap">{{ $i->expected !== null ? money($i->expected) : '—' }}</td>
                <td class="text-right whitespace-nowrap">{{ $i->actual !== null ? money($i->actual) : '—' }}</td>
                <td><span @class(['badge', 'badge-pending' => $i->status === 'open', 'badge-info' => $i->status === 'proposed', 'badge-success' => in_array($i->status, ['corrected', 'cleared']), 'bg-surface-2 text-muted' => $i->status === 'ignored'])>{{ ucfirst($i->status) }}</span></td>
                <td class="text-right"><a href="{{ route('admin.reconcile.show', $i) }}" class="btn h-9 text-xs">Review</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-10">No issues. 👍</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $issues->links() }}
@endsection
