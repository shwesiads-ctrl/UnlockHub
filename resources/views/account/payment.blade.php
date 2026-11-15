@extends('layouts.account', ['title' => 'Payment details'])

@section('content')
<a href="{{ route('account.statement') }}" class="text-sm self-start">{{ __('← Wallet statement') }}</a>
<div class="flex flex-wrap gap-5 items-start">
    <section class="card p-6 flex-[1_1_360px] min-w-0 flex flex-col gap-4">
        <div class="flex justify-between items-center gap-3">
            <h2 class="m-0 text-xl font-bold">Top-up #{{ $payment->id }}</h2>
            <x-status-badge :status="$payment->status" />
        </div>
        <dl class="m-0 grid gap-4 text-sm">
            <div><dt class="text-muted">{{ __('Payment method') }}</dt><dd class="m-0 font-semibold">{{ $payment->method?->name ?? '—' }}</dd></div>
            <div><dt class="text-muted">{{ __('Transaction ID') }}</dt><dd class="m-0 font-mono break-all">{{ $payment->transaction_id }}</dd></div>
            <div><dt class="text-muted">{{ __('Amount transferred') }}</dt><dd class="m-0 font-semibold">{{ pay_amount($payment->amount, $payment->currency) }}</dd></div>
            @if ($payment->status === 'approved')
                <div><dt class="text-muted">{{ __('Wallet credited') }}</dt><dd class="m-0 font-semibold text-ok">{{ money($payment->credited_amount) }}</dd></div>
            @endif
            <div><dt class="text-muted">{{ __('Submitted') }}</dt><dd class="m-0">{{ $payment->created_at->format('d M Y H:i') }}</dd></div>
            @if ($payment->reviewed_at)
                <div><dt class="text-muted">{{ __('Reviewed') }}</dt><dd class="m-0">{{ $payment->reviewed_at->format('d M Y H:i') }}</dd></div>
            @endif
            @if ($payment->status === 'rejected' && $payment->reject_reason)
                <div><dt class="text-muted">{{ __('Rejection reason') }}</dt><dd class="m-0 text-danger whitespace-pre-line">{{ $payment->reject_reason }}</dd></div>
            @endif
        </dl>
    </section>
    <section class="card p-6 flex-[2_1_480px] min-w-0 flex flex-col gap-4">
        <h2 class="m-0 text-xl font-bold">{{ __('Payment receipt') }}</h2>
        @if ($payment->receipt_path)
            <a href="{{ route('account.funds.receipt', $payment) }}" target="_blank" rel="noopener" class="self-start">{{ __('Open full-size receipt ↗') }}</a>
            <img src="{{ route('account.funds.receipt', $payment) }}" alt="Receipt for top-up #{{ $payment->id }}" class="block max-w-full max-h-[800px] object-contain rounded-xl">
        @else
            <p class="m-0 text-muted">{{ __('No receipt attached.') }}</p>
        @endif
    </section>
</div>
@endsection
