@extends('layouts.admin', ['title' => 'Top-up #'.$pr->id.' · '.$pr->user->username, 'subtitle' => 'Payments / Review'])

@section('content')
<a href="{{ route('admin.payments.index') }}" class="text-sm self-start">← Back to list</a>

<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 card p-5 flex flex-col gap-3">
        <div class="flex justify-between items-center">
            <h2 class="m-0 text-lg font-bold">Receipt</h2>
            <a href="{{ route('admin.payments.receipt', $pr) }}" target="_blank" rel="noopener" class="btn h-9 text-xs">Open full size</a>
        </div>
        <a href="{{ route('admin.payments.receipt', $pr) }}" target="_blank" rel="noopener" class="block rounded-xl bg-surface-2 border border-line overflow-hidden">
            <img src="{{ route('admin.payments.receipt', $pr) }}" alt="Receipt uploaded by {{ $pr->user->username }}" class="w-full max-h-[720px] object-contain">
        </a>
    </section>

    <aside class="flex-[1_1_360px] min-w-0 flex flex-col gap-5">
        <section class="card p-5 flex flex-col gap-3.5">
            <div class="flex justify-between items-center gap-3">
                <h2 class="m-0 text-lg font-bold">Details</h2>
                <x-status-badge :status="$pr->status" />
            </div>
            <dl class="m-0 grid grid-cols-2 gap-3 text-[13px]">
                <div><dt class="text-muted">User</dt><dd class="m-0 font-semibold">{{ $pr->user->username }} <span class="text-muted font-normal">({{ $pr->user->group?->name }})</span></dd></div>
                <div><dt class="text-muted">Method</dt><dd class="m-0 font-semibold">{{ $pr->method->name }} @if ($pr->method->country)<span class="text-muted font-normal">· {{ config('unlockhub.countries.'.$pr->method->country, $pr->method->country) }}</span>@endif</dd></div>
                <div><dt class="text-muted">Claimed amount</dt><dd class="m-0 font-semibold font-display text-base">{{ pay_amount($pr->amount, $pr->currency) }}</dd></div>
                @if ($pr->isForeign())
                    <div class="col-span-2 rounded-[10px] px-3 py-2.5 bg-brand-soft text-brand-ink">
                        <dt class="text-xs font-semibold">Paid in {{ $pr->currency }}</dt>
                        <dd class="m-0 font-display font-bold text-base">{{ pay_amount($pr->amount, $pr->currency) }} × {{ rtrim(rtrim(number_format((float) $pr->rate, 4), '0'), '.') }} = {{ money($pr->suggestedCredit()) }}</dd>
                        <dd class="m-0 text-xs">Rate at submit time. Current rate for {{ $pr->method->name }}: {{ rtrim(rtrim(number_format((float) $pr->method->rate, 4), '0'), '.') }} Ks.</dd>
                    </div>
                @endif
                <div><dt class="text-muted">Submitted</dt><dd class="m-0 font-semibold">{{ $pr->created_at->format('d M Y H:i') }}</dd></div>
                <div class="col-span-2"><dt class="text-muted">Transaction ID</dt><dd class="m-0 font-semibold font-mono flex items-center gap-2">{{ $pr->transaction_id }} <button type="button" class="btn h-7 px-2 text-[11px]" data-copy="{{ $pr->transaction_id }}">Copy</button></dd></div>
                <div class="col-span-2"><dt class="text-muted">User history</dt><dd class="m-0">{{ $userStats['approved_count'] }} approved ({{ money($userStats['approved_total']) }}) · {{ $userStats['rejected_count'] }} rejected · balance {{ money($pr->user->balance) }}</dd></div>
            </dl>

            @if ($others->isNotEmpty())
                <div class="rounded-[10px] px-3 py-2.5 bg-danger-soft text-danger text-[13px] leading-normal" role="alert">
                    <strong>Same transaction ID used before:</strong>
                    @foreach ($others as $o)
                        <a href="{{ route('admin.payments.show', $o) }}" class="text-danger underline">#{{ $o->id }} by {{ $o->user->username }} ({{ $o->status }})</a>@if (! $loop->last), @endif
                    @endforeach
                </div>
            @endif

            @if ($pr->status !== 'pending')
                <div class="rounded-[10px] px-3 py-2.5 bg-surface-2 text-[13px] leading-normal">
                    {{ ucfirst($pr->status) }} by {{ $pr->reviewer?->name ?? '—' }} on {{ $pr->reviewed_at?->format('d M Y H:i') }}.
                    @if ($pr->status === 'approved') Credited {{ money($pr->credited_amount) }}@if ($pr->isForeign()) (from {{ pay_amount($pr->amount, $pr->currency) }} at {{ rtrim(rtrim(number_format((float) $pr->rate, 4), '0'), '.') }})@endif. @endif
                    @if ($pr->reject_reason) Reason: {{ $pr->reject_reason }}. @endif
                    @if ($pr->admin_note) <br>Note: {{ $pr->admin_note }} @endif
                </div>
            @endif
        </section>

        @if ($pr->status === 'pending' && auth()->user()->canDo('payments.approve'))
            <form method="POST" action="{{ route('admin.payments.approve', $pr) }}" class="card p-5 flex flex-col gap-3.5">
                @csrf
                <h2 class="m-0 text-lg font-bold">Approve</h2>
                @if ($pr->isForeign())
                    <div class="grid grid-cols-2 gap-3" data-approve-calc data-amount="{{ (float) $pr->amount }}">
                        <div>
                            <label for="paid_amount" class="label">Received ({{ $pr->currency }})</label>
                            <input id="paid_amount" name="paid_amount" type="number" min="0.01" step="0.01" value="{{ old('paid_amount', (float) $pr->amount) }}" class="input font-display" data-calc-amount>
                        </div>
                        <div>
                            <label for="rate" class="label">Rate (Ks per 1 {{ $pr->currency }})</label>
                            <input id="rate" name="rate" type="number" min="0.0001" step="0.0001" value="{{ old('rate', (float) $pr->rate) }}" class="input font-display" data-calc-rate>
                            <x-field-error name="rate" />
                        </div>
                    </div>
                @endif
                <div>
                    <label for="credited_amount" class="label">Credit to wallet (Ks)</label>
                    <input id="credited_amount" name="credited_amount" type="number" min="1" step="1" value="{{ old('credited_amount', $pr->suggestedCredit()) }}" class="input font-display text-lg" required data-calc-result>
                    <x-field-error name="credited_amount" />
                    <p class="m-0 mt-1.5 text-xs text-muted">{{ $pr->isForeign() ? 'Filled in as received × rate. You can still type a different Ks amount.' : 'Change it if the real transfer is a different amount.' }}</p>
                </div>
                <div>
                    <label for="approve_note" class="label">Internal note <span class="font-normal text-muted">(optional)</span></label>
                    <input id="approve_note" name="admin_note" type="text" class="input h-11">
                </div>
                <button class="btn btn-success btn-lg">Approve &amp; credit</button>
            </form>

            <form method="POST" action="{{ route('admin.payments.reject', $pr) }}" class="card p-5 flex flex-col gap-3.5">
                @csrf
                <h2 class="m-0 text-lg font-bold">Reject</h2>
                <div>
                    <label for="reject_reason" class="label">Reason <span class="font-normal text-muted">(shown to user)</span></label>
                    <select id="reject_reason" name="reject_reason" class="input" required>
                        <option value="">Select…</option>
                        @foreach ($reasons as $reason)
                            <option @selected(old('reject_reason') === $reason)>{{ $reason }}</option>
                        @endforeach
                        <option value="other" @selected(old('reject_reason') === 'other')>Other…</option>
                    </select>
                    <x-field-error name="reject_reason" />
                </div>
                <div>
                    <label for="custom_reason" class="label">Other reason</label>
                    <input id="custom_reason" name="custom_reason" type="text" value="{{ old('custom_reason') }}" class="input h-11" placeholder="Only if you chose Other">
                    <x-field-error name="custom_reason" />
                </div>
                <button class="btn btn-danger btn-lg">Reject</button>
            </form>
        @endif
    </aside>
</div>
@endsection
