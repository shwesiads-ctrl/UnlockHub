@extends('layouts.account', ['title' => __('Order').' #'.$order->order_no])

@section('content')
<a href="{{ route('account.orders.index') }}" class="text-sm self-start">{{ __('← All orders') }}</a>

<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 flex flex-col gap-5">
        <div class="card p-6 flex flex-col gap-4">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <h2 class="m-0 text-xl font-bold">{{ $order->service_name }}</h2>
                <x-status-badge :status="$order->status" class="text-sm" />
            </div>

            @if ($order->status === 'success')
                <div class="rounded-xl p-4 bg-ok-soft flex flex-col gap-2">
                    <span class="text-sm font-semibold text-ok">{{ __('Result') }}</span>
                    <pre class="m-0 font-mono text-[15px] text-ink whitespace-pre-wrap break-all">{{ $order->result }}</pre>
                    <button type="button" class="btn h-9 text-[13px] self-start" data-copy="{{ $order->result }}">{{ __('Copy result') }}</button>
                </div>
            @elseif ($order->status === 'rejected')
                <div class="rounded-xl p-4 bg-danger-soft text-danger text-sm leading-relaxed">
                    <strong>{{ __('Rejected:') }}</strong> {{ $order->result ?: __('No reason given.') }}
                    @if ($order->refunded) <br>{{ __(':amount was refunded to your wallet.', ['amount' => money($order->price)]) }} @endif
                </div>
            @elseif ($order->status === 'cancelled')
                <div class="rounded-xl p-4 bg-surface-2 text-sm">{{ __('Cancelled.') }} {{ __(':amount was refunded to your wallet.', ['amount' => money($order->price)]) }}</div>
            @else
                <div class="rounded-xl p-4 bg-brand-soft text-brand-ink text-sm leading-relaxed">
                    {{ __('We\'re working on it. Estimated delivery:') }} <strong>{{ $order->service?->delivery_time ?: '—' }}</strong>. {{ __('This page shows the result as soon as it\'s ready.') }}
                </div>
            @endif

            <dl class="m-0 grid gap-3 grid-cols-[repeat(auto-fit,minmax(180px,1fr))] text-sm">
                @foreach ($order->input as $key => $value)
                    @php $label = $order->service?->fields->firstWhere('name', $key)?->label ?? ucfirst($key); @endphp
                    <div><dt class="text-muted">{{ $label }}</dt><dd class="m-0 font-mono font-semibold break-all">{{ $value }}</dd></div>
                @endforeach
                @if ($order->user_note)
                    <div class="col-span-full"><dt class="text-muted">{{ __('Your note') }}</dt><dd class="m-0">{{ $order->user_note }}</dd></div>
                @endif
            </dl>
        </div>
    </section>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-4">
        <div class="card p-5 flex flex-col gap-3 text-sm">
            <div class="flex justify-between"><span class="text-muted">{{ __('Order no.') }}</span><span class="font-mono font-semibold">{{ $order->order_no }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ __('Placed') }}</span><span>{{ $order->created_at->format('d M Y H:i') }}</span></div>
            @if ($order->completed_at)
                <div class="flex justify-between"><span class="text-muted">{{ __('Finished') }}</span><span>{{ $order->completed_at->format('d M Y H:i') }}</span></div>
            @endif
            <div class="flex justify-between"><span class="text-muted">{{ __('Price') }}</span><span class="font-semibold">{{ price($order->price) }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ __('Points') }}</span><span class="font-semibold text-point">{{ $order->points_awarded ? '+'.$order->points.' earned' : '+'.$order->points.' on success' }}</span></div>
            @if ($order->batch_id)
                <a href="{{ route('account.orders.index', ['batch' => $order->batch_id]) }}" class="text-[13px]">{{ __('View the whole bulk order') }}</a>
            @endif
        </div>

        @if ($order->status === 'pending' && $order->service?->allow_cancel_pending && ! $order->supplier_order_id)
            <form method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order and refund {{ money($order->price) }}?')">
                @csrf
                <button class="btn btn-danger w-full">{{ __('Cancel order & refund') }}</button>
            </form>
        @endif

        <a href="{{ route('account.tickets.create', ['order' => $order->order_no]) }}" class="btn w-full"><x-icon name="chat" size="16" />{{ __('Get help with this order') }}</a>

        @if ($order->service?->status === 'active')
            <a href="{{ route('account.orders.reorder', $order) }}" class="btn w-full">{{ $order->batch_id ? __('Order this bulk again') : __('Order again') }}</a>
        @endif
    </aside>
</div>
@endsection
