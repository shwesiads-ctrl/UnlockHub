@extends('layouts.app', ['title' => 'Track Order'])

@section('content')
<section class="max-w-2xl mx-auto px-4 py-14 flex flex-col gap-5">
    <div class="flex flex-col gap-1.5">
        <h1 class="m-0 text-[34px] font-bold">{{ __('Track an order') }}</h1>
        <p class="m-0 text-muted text-[15px]">{{ __('Enter the order number (e.g. UH2611151234567). Logged-in owners also see the result.') }}</p>
    </div>

    <form method="GET" class="card p-4 flex flex-wrap gap-2">
        <label for="order" class="sr-only">{{ __('Order number') }}</label>
        <input id="order" name="order" value="{{ $query }}" class="input flex-[1_1_260px] font-mono uppercase" placeholder="{{ __('Order number') }}" required autofocus>
        <button class="btn btn-primary btn-lg">{{ __('Track') }}</button>
        <x-field-error name="order" />
    </form>

    @if ($query !== '')
        @if (! $order)
            <div class="card p-6 text-center text-muted">{{ __('No order found with number') }} <span class="font-mono text-ink">{{ $query }}</span>.</div>
        @else
            @php
                $imei = (string) $order->imei;
                $masked = strlen($imei) > 7 ? substr($imei, 0, 4).str_repeat('•', strlen($imei) - 7).substr($imei, -3) : $imei;
            @endphp
            <div class="card p-6 flex flex-col gap-4">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <span class="font-mono font-bold text-lg">#{{ $order->order_no }}</span>
                    <x-status-badge :status="$order->status" class="text-sm" />
                </div>
                <dl class="m-0 grid gap-3 grid-cols-2 text-sm">
                    <div class="col-span-2"><dt class="text-muted">{{ __('Service') }}</dt><dd class="m-0 font-semibold">{{ $order->service_name }}</dd></div>
                    <div><dt class="text-muted">{{ __('Item') }}</dt><dd class="m-0 font-mono">{{ $masked ?: '—' }}</dd></div>
                    <div><dt class="text-muted">{{ __('Placed') }}</dt><dd class="m-0">{{ $order->created_at->format('d M Y H:i') }}</dd></div>
                    @if ($order->completed_at)
                        <div><dt class="text-muted">{{ __('Finished') }}</dt><dd class="m-0">{{ $order->completed_at->format('d M Y H:i') }}</dd></div>
                    @endif
                </dl>
                @if ($isOwner)
                    <a href="{{ route('account.orders.show', $order) }}" class="btn btn-primary self-start">{{ __('View result') }}</a>
                @elseif (in_array($order->status, ['success', 'rejected']))
                    <p class="m-0 text-sm text-muted">{{ __('Log in as the order owner to see the result.') }}</p>
                @endif
            </div>
        @endif
    @endif
</section>
@endsection
