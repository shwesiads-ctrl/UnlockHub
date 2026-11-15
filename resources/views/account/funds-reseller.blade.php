@extends('layouts.account', ['title' => __('Add Funds')])

@section('content')
@php $p = $reseller->resellerProfile; @endphp
<section class="card p-6 flex flex-col gap-3 max-w-2xl">
    <h2 class="m-0 text-xl font-bold">{{ __('Top up with') }} {{ $p->shop_name }}</h2>
    <p class="m-0 text-sm text-muted">{{ __('Your account is managed by') }} <strong>{{ $p->shop_name }}</strong>. {{ __('Pay them, and they add the balance to your wallet.') }}</p>
    <div class="flex flex-wrap gap-2">
        @if ($p->contact_phone) <a href="tel:{{ preg_replace('/[^0-9+]/', '', $p->contact_phone) }}" class="btn">📞 {{ $p->contact_phone }}</a> @endif
        @if ($p->contact_telegram) <a href="https://t.me/{{ ltrim($p->contact_telegram, '@') }}" target="_blank" rel="noopener" class="btn">Telegram {{ $p->contact_telegram }}</a> @endif
        @if ($p->contact_email) <a href="mailto:{{ $p->contact_email }}" class="btn">{{ $p->contact_email }}</a> @endif
    </div>
    <p class="m-0 text-sm">{{ __('Current balance') }}: <strong>{{ money(auth()->user()->balance) }}</strong></p>
</section>
@endsection
