@extends('layouts.admin', ['title' => 'Withdrawals', 'subtitle' => 'Commission and reseller profit payouts'])

@section('content')
<div class="flex flex-wrap gap-2">
    @foreach (['pending' => 'Waiting', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $k => $l)
        <a href="{{ route('admin.withdrawals.index', ['status' => $k]) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => $status === $k])>{{ $l }}</a>
    @endforeach
</div>
<section class="card table-wrap">
    <table class="table min-w-[860px]">
        <thead><tr><th>#</th><th>Date</th><th>User</th><th class="text-right">Amount</th><th>Pay to</th><th></th></tr></thead>
        <tbody>
        @forelse ($items as $w)
            <tr>
                <td class="text-muted">{{ $w->id }}</td>
                <td class="whitespace-nowrap text-muted">{{ $w->created_at->format('d M H:i') }}</td>
                <td><a href="{{ route('admin.users.show', $w->user_id) }}">{{ $w->user?->username }}</a></td>
                <td class="text-right font-bold">{{ money($w->amount) }}</td>
                <td><span class="font-semibold">{{ $w->method }}</span><div class="text-xs font-mono select-all">{{ $w->account }}</div></td>
                <td>
                    @if ($w->status === 'pending')
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.withdrawals.pay', $w) }}" class="flex gap-1" onsubmit="return confirm('Did you send {{ money($w->amount) }} to this account?')">@csrf<input name="reference" class="input h-9 w-36 text-xs" placeholder="Txn / reference" aria-label="Payout reference"><button class="btn btn-primary h-9 text-xs">Mark paid</button></form>
                            <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}" class="flex gap-1">@csrf<input name="reason" class="input h-9 w-32 text-xs" placeholder="Reason" required aria-label="Reason"><button class="btn btn-danger h-9 text-xs">Reject</button></form>
                        </div>
                    @else
                        <span class="text-xs text-muted">{{ $w->reviewer?->username }} · {{ $w->reviewed_at?->format('d M H:i') }} {{ $w->payout_reference }} {{ $w->admin_note }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-10">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $items->links() }}
@endsection
