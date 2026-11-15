@extends('layouts.admin', ['title' => 'Issue #'.$issue->id, 'subtitle' => 'Wallet reconciliation / '.$issue->typeLabel()])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_560px] min-w-0 flex flex-col gap-4">
        <div class="card p-5 flex flex-col gap-2">
            <div class="flex flex-wrap gap-2 items-center">
                <span class="badge {{ $issue->severity === 'high' ? 'badge-rejected' : 'badge-pending' }}">{{ ucfirst($issue->severity) }}</span>
                <strong>{{ $issue->typeLabel() }}</strong>
                <span class="ml-auto badge badge-info">{{ ucfirst($issue->status) }}</span>
            </div>
            <p class="m-0">{{ $issue->message }}</p>
            <div class="flex flex-wrap gap-6 text-sm">
                <span>Expected: <strong>{{ $issue->expected !== null ? money($issue->expected) : '—' }}</strong></span>
                <span>Actual: <strong>{{ $issue->actual !== null ? money($issue->actual) : '—' }}</strong></span>
                @if ($issue->expected !== null && $issue->actual !== null) <span>Difference: <strong>{{ money((float) $issue->actual - (float) $issue->expected) }}</strong></span> @endif
            </div>
            <div class="text-xs text-muted">Found {{ $issue->created_at->format('d M Y H:i') }} · last seen in run #{{ $issue->last_run_id }}
                @if ($issue->subject_type === 'order' && $issue->subject_id) · <a href="{{ route('admin.orders.show', \App\Models\Order::find($issue->subject_id) ?? 0) }}">open order</a> @endif
                @if ($issue->subject_type === 'payment_request' && $issue->subject_id) · <a href="{{ route('admin.payments.show', $issue->subject_id) }}">open top-up</a> @endif
            </div>
        </div>

        @if ($issue->user)
            <section class="card overflow-hidden">
                <div class="px-5 py-4 flex flex-wrap gap-4 items-center">
                    <h2 class="m-0 text-lg font-bold mr-auto">{{ $issue->user->username }}'s wallet</h2>
                    <span class="text-sm">Balance <strong>{{ money($issue->user->balance) }}</strong></span>
                    <span class="text-sm">Ledger <strong>{{ money($ledger) }}</strong></span>
                </div>
                <div class="table-wrap">
                    <table class="table min-w-[600px]">
                        <thead><tr><th>ID</th><th>Date</th><th>Type</th><th>Description</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead>
                        <tbody>
                        @foreach ($recent as $t)
                            <tr @class(['bg-danger-soft' => $issue->subject_type === 'wallet_transaction' && $issue->subject_id === $t->id])>
                                <td class="text-muted">{{ $t->id }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $t->created_at->format('d M H:i') }}</td>
                                <td>{{ $t->type }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($t->description, 50) }}</td>
                                <td class="text-right">{{ money($t->amount) }}</td>
                                <td class="text-right">{{ money($t->balance_after) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </section>

    <aside class="flex-[1_1_340px] min-w-0 flex flex-col gap-4">
        @if ($issue->status === 'proposed')
            <div class="card p-5 flex flex-col gap-3 text-sm">
                <h2 class="m-0 text-lg font-bold">Proposed correction</h2>
                <div>{{ \App\Models\ReconciliationIssue::CORRECTIONS[$issue->correction] }}@if ($issue->correction_amount) — <strong>{{ money($issue->correction_amount) }}</strong>@endif</div>
                <div class="text-muted">Reason: {{ $issue->reason }}</div>
                <div class="text-muted">By {{ $issue->proposer?->username }}</div>
                @can('reconcile.approve')
                    @if ($issue->proposed_by === auth()->id())
                        <p class="m-0 text-xs text-muted">Another staff member must approve.</p>
                        <form method="POST" action="{{ route('admin.reconcile.reject', $issue) }}">@csrf<button class="btn h-9 text-xs">Withdraw</button></form>
                    @else
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.reconcile.approve', $issue) }}" onsubmit="return confirm('Apply this correction to the wallet?')">@csrf<button class="btn btn-primary">Approve &amp; apply</button></form>
                            <form method="POST" action="{{ route('admin.reconcile.reject', $issue) }}">@csrf<button class="btn btn-danger">Reject</button></form>
                        </div>
                    @endif
                @endcan
            </div>
        @elseif ($issue->status === 'open')
            @can('reconcile.approve')
            <form method="POST" action="{{ route('admin.reconcile.propose', $issue) }}" class="card p-5 flex flex-col gap-3">
                @csrf
                <h2 class="m-0 text-lg font-bold">Propose a correction</h2>
                <p class="m-0 text-xs text-muted">Nothing changes until a different staff member approves.</p>
                <div><label for="correction" class="label text-xs">Correction</label>
                    <select id="correction" name="correction" class="input h-10">
                        @foreach (\App\Models\ReconciliationIssue::CORRECTIONS as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select></div>
                <div><label for="amount" class="label text-xs">Amount (Ks) — not needed for "Set balance"</label>
                    <input id="amount" name="amount" type="number" min="0" step="0.01" class="input h-10" value="{{ $issue->expected !== null && $issue->actual !== null ? abs((float) $issue->actual - (float) $issue->expected) : '' }}"></div>
                <div><label for="reason" class="label text-xs">Reason (kept in the log)</label><input id="reason" name="reason" class="input h-10" required minlength="5" maxlength="255"></div>
                <button class="btn btn-primary">Propose</button>
            </form>
            <form method="POST" action="{{ route('admin.reconcile.ignore', $issue) }}" class="card p-5 flex flex-col gap-3">
                @csrf
                <h2 class="m-0 text-lg font-bold">Checked — no correction</h2>
                <div><label for="ireason" class="label text-xs">Why it is fine</label><input id="ireason" name="reason" class="input h-10" required minlength="5" maxlength="255"></div>
                <button class="btn">Mark as checked</button>
            </form>
            @endcan
        @else
            <div class="card p-5 flex flex-col gap-2 text-sm">
                <h2 class="m-0 text-lg font-bold">{{ ucfirst($issue->status) }}</h2>
                @if ($issue->correction) <div>{{ \App\Models\ReconciliationIssue::CORRECTIONS[$issue->correction] }}@if ($issue->correction_amount) — {{ money($issue->correction_amount) }}@endif</div> @endif
                @if ($issue->reason) <div class="text-muted">Reason: {{ $issue->reason }}</div> @endif
                <div class="text-muted">
                    @if ($issue->proposer) Proposed by {{ $issue->proposer->username }} · @endif
                    @if ($issue->approver) {{ $issue->status === 'ignored' ? 'Checked' : 'Approved' }} by {{ $issue->approver->username }} · @endif
                    {{ $issue->resolved_at?->format('d M Y H:i') }}
                </div>
            </div>
        @endif
    </aside>
</div>
@endsection
