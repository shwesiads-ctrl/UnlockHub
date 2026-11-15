@extends('layouts.account', ['title' => 'Statement'])

@section('content')
@php $q = ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'type' => $type]; @endphp

<form method="GET" class="card p-4 flex flex-wrap gap-3 items-end">
    <div class="flex flex-wrap gap-2">
        @foreach ([
            'This month' => [now()->startOfMonth(), now()],
            'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'Last 30 days' => [now()->subDays(29), now()],
            'This year' => [now()->startOfYear(), now()],
        ] as $label => [$f, $t])
            <a href="{{ route('account.statement', ['from' => $f->toDateString(), 'to' => $t->toDateString(), 'type' => $type]) }}"
               @class(['btn h-9 text-[13px]', 'btn-primary' => $from->isSameDay($f) && $to->isSameDay($t)])>{{ $label }}</a>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-2 items-end ml-auto">
        <div><label for="from" class="label text-xs">From</label><input id="from" name="from" type="date" value="{{ $from->toDateString() }}" class="input h-10"></div>
        <div><label for="to" class="label text-xs">To</label><input id="to" name="to" type="date" value="{{ $to->toDateString() }}" class="input h-10"></div>
        <div>
            <label for="type" class="label text-xs">Type</label>
            <select id="type" name="type" class="input h-10 w-auto">
                <option value="">All</option>
                @foreach ($types as $k => $label) <option value="{{ $k }}" @selected($type === $k)>{{ $label }}</option> @endforeach
            </select>
        </div>
        <button class="btn h-10">Show</button>
    </div>
</form>

<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(180px,1fr))]">
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] font-semibold text-muted">Opening balance</span><span class="font-display text-2xl font-bold">{{ money($summary['opening']) }}</span><span class="text-xs text-muted">{{ $from->format('d M Y') }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] font-semibold text-muted">Money in</span><span class="font-display text-2xl font-bold text-ok">+{{ money($summary['credits']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] font-semibold text-muted">Money out</span><span class="font-display text-2xl font-bold text-danger">−{{ money($summary['debits']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] font-semibold text-muted">Closing balance</span><span class="font-display text-2xl font-bold">{{ money($summary['closing']) }}</span><span class="text-xs text-muted">{{ $to->format('d M Y') }}</span></div>
</div>

<div class="flex flex-wrap gap-2 items-center">
    <a href="{{ route('account.statement.pdf', $q) }}" class="btn btn-primary"><x-icon name="file" size="16" />Download PDF</a>
    <form method="POST" action="{{ route('account.statement.email') }}">
        @csrf
        @foreach ($q as $k => $v) @if ($v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif @endforeach
        <button class="btn" @disabled(! auth()->user()->email) @unless (auth()->user()->email) title="Add your email in My Profile first" @endunless>
            <x-icon name="bell" size="16" />Email PDF to {{ auth()->user()->email ? \Illuminate\Support\Str::mask(auth()->user()->email, '•', 2, max(1, strpos(auth()->user()->email, '@') - 2)) : 'me' }}
        </button>
    </form>
    @if (setting('statements.monthly', '1') === '1' && auth()->user()->notify_email && auth()->user()->email)
        <span class="text-xs text-muted ml-auto">Your monthly statement is emailed on the 1st of each month.</span>
    @endif
</div>

<section class="card table-wrap">
    <table class="table min-w-[680px]">
        <thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead>
        <tbody>
        @forelse ($transactions as $t)
            <tr>
                <td class="whitespace-nowrap text-muted">{{ $t->created_at->format('d M Y H:i') }}</td>
                <td><span @class(['badge', 'badge-success' => $t->amount > 0, 'badge-rejected' => $t->amount < 0])>{{ $types[$t->type] ?? $t->type }}</span></td>
                <td>
                    @if ($t->reference instanceof \App\Models\Order && $t->reference->user_id === auth()->id())
                        <a href="{{ route('account.orders.show', $t->reference) }}" class="block hover:underline">
                            {{ $t->description }}
                            <span class="block text-xs text-brand-ink mt-1">View order details →</span>
                        </a>
                    @elseif ($t->reference instanceof \App\Models\PaymentRequest && $t->reference->user_id === auth()->id())
                        <a href="{{ route('account.funds.show', $t->reference) }}" class="block hover:underline">
                            {{ $t->description }}
                            <span class="block text-xs text-brand-ink mt-1">View payment details &amp; receipt →</span>
                        </a>
                    @else
                        {{ $t->description }}
                    @endif
                </td>
                <td @class(['text-right font-semibold whitespace-nowrap', 'text-ok' => $t->amount > 0, 'text-danger' => $t->amount < 0])>{{ $t->amount > 0 ? '+' : '−' }}{{ money(abs($t->amount)) }}</td>
                <td class="text-right whitespace-nowrap">{{ money($t->balance_after) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-10">No transactions in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

{{ $transactions->links() }}
@endsection
