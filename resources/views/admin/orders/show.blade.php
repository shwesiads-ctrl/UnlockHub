@extends('layouts.admin', ['title' => 'Order #'.$order->order_no, 'subtitle' => $order->service_name])

@section('content')
<div class="flex flex-wrap gap-3 items-center">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.orders.index') }}" class="text-sm">← Back</a>
    @if ($nextManual && $order->processing_mode === 'manual')
        <a href="{{ route('admin.orders.show', $nextManual) }}" class="btn h-9 text-xs ml-auto">Skip to next in queue →</a>
    @endif
</div>

<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 flex flex-col gap-5">
        <div class="card p-6 flex flex-col gap-4">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <h2 class="m-0 text-lg font-bold">Order details</h2>
                <div class="flex gap-2"><x-status-badge :status="$order->processing_mode" /><x-status-badge :status="$order->status" /></div>
            </div>

            <dl class="m-0 grid gap-3 grid-cols-[repeat(auto-fit,minmax(180px,1fr))] text-sm p-4 rounded-xl bg-surface-2">
                <div><dt class="text-muted">User</dt><dd class="m-0 font-semibold">{{ $order->user->username }} <span class="font-normal text-muted">({{ $order->user->group?->name }})</span></dd></div>
                <div><dt class="text-muted">Charged</dt><dd class="m-0 font-semibold">{{ $order->priceLabel() }} <span class="font-normal text-muted">· cost {{ money($order->cost) }}</span></dd></div>
                <div><dt class="text-muted">Placed</dt><dd class="m-0 font-semibold">{{ $order->created_at->format('d M Y H:i') }}</dd></div>
                <div><dt class="text-muted">Points on success</dt><dd class="m-0 font-semibold text-point">+{{ $order->points }}</dd></div>
                @foreach ($order->input as $key => $value)
                    @php $label = $order->service?->fields->firstWhere('name', $key)?->label ?? ucfirst($key); @endphp
                    <div class="col-span-full sm:col-span-1">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="m-0 font-mono font-semibold flex items-center gap-2 break-all">{{ $value }} <button type="button" class="btn h-7 px-2 text-[11px] font-sans" data-copy="{{ $value }}">Copy</button></dd>
                    </div>
                @endforeach
                @if ($order->user_note)
                    <div class="col-span-full"><dt class="text-muted">User note</dt><dd class="m-0">{{ $order->user_note }}</dd></div>
                @endif
                @if ($order->supplier)
                    <div><dt class="text-muted">Supplier</dt><dd class="m-0 font-semibold">{{ $order->supplier->name }}</dd></div>
                    <div><dt class="text-muted">Supplier ref</dt><dd class="m-0 font-mono">{{ $order->supplier_order_id ?: '—' }}</dd></div>
                @endif
                @if ($batchCount > 1)
                    <div class="col-span-full"><dd class="m-0"><a href="{{ route('admin.orders.index', ['batch' => $order->batch_id]) }}" class="text-[13px]">Part of a bulk order ({{ $batchCount }} items)</a></dd></div>
                @endif
            </dl>

            @if ($order->api_failed || $order->api_error)
                <div class="rounded-[10px] px-3 py-2.5 bg-danger-soft text-danger text-[13px] leading-normal" role="alert">
                    <strong>API error</strong> after {{ $order->api_attempts }} attempt(s): {{ $order->api_error }}
                </div>
            @endif

            @unless ($order->isOpen())
                <div @class(['rounded-xl p-4 text-sm leading-relaxed', 'bg-ok-soft' => $order->status === 'success', 'bg-danger-soft text-danger' => $order->status !== 'success'])>
                    <strong>{{ $order->statusLabel() }}</strong>
                    @if ($order->processor) by {{ $order->processor->name }} @endif
                    on {{ $order->completed_at?->format('d M Y H:i') }}
                    @if ($order->refunded) · refunded @endif
                    @if ($order->points_awarded) · +{{ $order->points }} points given @endif
                    @if ($order->result)
                        <pre class="m-0 mt-2 font-mono text-[14px] text-ink whitespace-pre-wrap break-all">{{ $order->result }}</pre>
                    @endif
                </div>
            @endunless
        </div>
    </section>

    <aside class="flex-[1_1_360px] min-w-0 flex flex-col gap-5">
        @if ($order->isOpen() && auth()->user()->canDo('orders.process'))
            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="card p-5 flex flex-col gap-3.5">
                @csrf @method('PUT')
                <h2 class="m-0 text-lg font-bold">Process</h2>
                <div>
                    <label for="result" class="label">Result / code <span class="font-normal text-muted">(sent to user)</span></label>
                    <textarea id="result" name="result" rows="4" class="input font-mono text-sm" placeholder="Success: e.g. NCK: 18461234  MCK: 77201234&#10;Reject: reason the user will see">{{ old('result') }}</textarea>
                    <x-field-error name="result" />
                </div>
                <div>
                    <label for="admin_note" class="label">Internal note</label>
                    <input id="admin_note" name="admin_note" value="{{ old('admin_note', $order->admin_note) }}" class="input h-11" placeholder="Only admins see this">
                </div>
                <label class="flex items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="go_next" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" checked>
                    Open next order in the queue after Success / Reject
                </label>
                <div class="flex gap-2 flex-wrap">
                    @if ($order->status === 'pending')
                        <button name="action" value="in_process" class="btn flex-1 bg-brand-soft text-brand-ink border-brand">In Process</button>
                    @endif
                    @if ($order->api_failed && $order->service?->usesApi())
                        <button name="action" value="retry_api" class="btn flex-1">Retry via API</button>
                    @endif
                    <button name="action" value="note" class="btn flex-1">Save note</button>
                </div>
                <div class="flex gap-2">
                    <button name="action" value="reject" class="btn btn-danger flex-1 h-12" onclick="return confirm('Reject and refund {{ $order->priceLabel() }}?')">Reject &amp; refund</button>
                    <button name="action" value="success" class="btn btn-success flex-1 h-12">Mark success</button>
                </div>
                <p class="m-0 text-xs text-muted leading-normal">Success gives the user +{{ $order->points }} points. Reject returns {{ $order->priceLabel() }} automatically.</p>
            </form>
        @else
            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="card p-5 flex flex-col gap-3">
                @csrf @method('PUT')
                <input type="hidden" name="action" value="note">
                <label for="admin_note" class="label mb-0">Internal note</label>
                <input id="admin_note" name="admin_note" value="{{ $order->admin_note }}" class="input h-11">
                <button class="btn self-start">Save note</button>
            </form>
        @endif
    </aside>
</div>
@endsection
