@extends('layouts.account', ['title' => __('Support')])

@section('content')
<div class="flex flex-wrap justify-between items-center gap-3">
    <p class="m-0 text-sm text-muted">{{ __('Ask about an order, a payment or your account. We reply here and by email.') }}</p>
    <a href="{{ route('account.tickets.create') }}" class="btn btn-primary">+ {{ __('New ticket') }}</a>
</div>

<section class="card table-wrap">
    <table class="table min-w-[640px]">
        <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Order') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last update') }}</th></tr></thead>
        <tbody>
        @forelse ($tickets as $t)
            <tr>
                <td class="font-mono"><a href="{{ route('account.tickets.show', $t) }}">#{{ $t->ticket_no }}</a></td>
                <td><a href="{{ route('account.tickets.show', $t) }}" class="text-ink font-semibold">{{ $t->subject }}</a><div class="text-xs text-muted">{{ \App\Models\Ticket::CATEGORIES[$t->category] }}</div></td>
                <td class="font-mono text-[13px]">{{ $t->order ? '#'.$t->order->order_no : '—' }}</td>
                <td><span class="badge {{ $t->badgeClass() }}">{{ $t->statusLabel() }}</span></td>
                <td class="text-muted whitespace-nowrap">{{ $t->last_reply_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-10">{{ __('No tickets yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $tickets->links() }}
@endsection
