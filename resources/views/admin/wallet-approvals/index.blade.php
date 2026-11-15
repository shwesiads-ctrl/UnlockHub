@extends('layouts.admin', ['title' => 'Wallet approvals', 'subtitle' => 'Large manual wallet changes need a second staff member'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l)
        <a href="{{ route('admin.wallet-approvals.index', ['status' => $k]) }}" @class(['btn h-9 text-sm', 'btn-primary' => $status === $k])>{{ $l }}</a>
    @endforeach
    <span class="ml-auto text-[13px] text-muted">
        @if ($threshold > 0) Adjustments of {{ money($threshold) }} or more need approval. @else Approval threshold is off. @endif
        @can('staff.manage') <a href="{{ route('admin.staff.index') }}#threshold">Change</a> @endcan
    </span>
</div>

<section class="card table-wrap">
    <table class="table min-w-[860px]">
        <thead><tr><th>Date</th><th>Customer</th><th class="text-right">Amount</th><th>Reason</th><th>Asked by</th><th>{{ $status === 'pending' ? '' : 'Reviewed by' }}</th></tr></thead>
        <tbody>
        @forelse ($items as $a)
            <tr>
                <td class="whitespace-nowrap text-muted">{{ $a->created_at->format('d M H:i') }}</td>
                <td><a href="{{ route('admin.users.show', $a->user) }}">{{ $a->user->username }}</a><div class="text-xs text-muted">Balance {{ money($a->user->balance) }}</div></td>
                <td @class(['text-right font-bold whitespace-nowrap', 'text-ok' => $a->direction === 'credit', 'text-danger' => $a->direction === 'debit'])>{{ $a->direction === 'credit' ? '+' : '−' }}{{ money($a->amount) }}</td>
                <td>{{ $a->reason }} @if ($a->kind === 'reconcile') <span class="badge badge-info">Reconciliation</span> @endif</td>
                <td class="text-muted">{{ $a->requester?->username }}</td>
                <td>
                    @if ($a->status === 'pending')
                        @if ($a->requested_by === auth()->id())
                            <span class="text-xs text-muted">Another staff member must approve</span>
                        @else
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.wallet-approvals.approve', $a) }}" onsubmit="return confirm('Apply this wallet change?')">@csrf<button class="btn btn-primary h-9 text-xs">Approve</button></form>
                                <form method="POST" action="{{ route('admin.wallet-approvals.reject', $a) }}" class="flex gap-1">@csrf<input name="note" class="input h-9 w-32 text-xs" placeholder="Reason" aria-label="Reject reason"><button class="btn btn-danger h-9 text-xs">Reject</button></form>
                            </div>
                        @endif
                    @else
                        <span class="text-muted">{{ $a->reviewer?->username }} · {{ $a->reviewed_at?->format('d M H:i') }}</span>
                        @if ($a->review_note) <div class="text-xs text-muted">{{ $a->review_note }}</div> @endif
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
