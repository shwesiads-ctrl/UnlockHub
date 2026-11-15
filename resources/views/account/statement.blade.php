@extends('layouts.account', ['title' => 'Statement'])

@section('content')
<nav aria-label="Filter" class="flex flex-wrap gap-2">
    <a href="{{ route('account.statement') }}" @class(['btn h-9 text-[13px]', 'btn-primary' => ! $type])>All</a>
    @foreach ($types as $k => $label)
        <a href="{{ route('account.statement', ['type' => $k]) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => $type === $k])>{{ $label }}</a>
    @endforeach
</nav>

<section class="card table-wrap">
    <table class="table min-w-[680px]">
        <thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead>
        <tbody>
        @forelse ($transactions as $t)
            <tr>
                <td class="whitespace-nowrap text-muted">{{ $t->created_at->format('d M Y H:i') }}</td>
                <td><span @class(['badge', 'badge-success' => $t->amount > 0, 'badge-rejected' => $t->amount < 0])>{{ $types[$t->type] ?? $t->type }}</span></td>
                <td>{{ $t->description }}</td>
                <td @class(['text-right font-semibold whitespace-nowrap', 'text-ok' => $t->amount > 0, 'text-danger' => $t->amount < 0])>{{ $t->amount > 0 ? '+' : '−' }}{{ money(abs($t->amount)) }}</td>
                <td class="text-right whitespace-nowrap">{{ money($t->balance_after) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-10">No transactions yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

{{ $transactions->links() }}
@endsection
