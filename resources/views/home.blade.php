@extends('layouts.app')

@section('content')
<section class="max-w-7xl mx-auto px-4 sm:px-8 pt-16 pb-12 flex flex-wrap gap-12 items-center">
    <div class="flex-[1_1_520px] min-w-0 flex flex-col gap-5">
        <span class="self-start badge badge-info text-[13px]">{{ __('For repair shops & resellers in Myanmar') }}</span>
        <h1 class="m-0 text-4xl sm:text-[56px] leading-[1.05] font-bold tracking-tight">{{ __('Unlock codes, tool credits & activations — in one wallet.') }}</h1>
        <p class="m-0 text-lg leading-relaxed text-muted max-w-xl">{{ __('Top up with KBZPay, WavePay or AYA Pay, place IMEI and server orders, track every result, and earn points on each successful order.') }}</p>
        <form role="search" action="{{ route('services.index') }}" class="flex flex-wrap gap-2 max-w-xl">
            <label for="hsearch" class="sr-only">Search services</label>
            <div class="flex-[1_1_320px] flex items-center gap-2.5 h-[52px] px-4 rounded-xl border border-line bg-surface text-muted">
                <x-icon name="search" />
                <input id="hsearch" name="q" type="text" placeholder="{{ __('Search: Samsung NCK, UnlockTool, AT&T…') }}" class="flex-1 bg-transparent outline-none text-ink text-[15px]">
            </div>
            <button class="btn btn-primary btn-lg">{{ __('Browse services') }}</button>
        </form>
        <div class="flex flex-wrap gap-5 text-sm text-muted">
            <span class="flex items-center gap-1.5"><x-icon name="check" size="16" class="text-ok" />{{ __('Auto refund on rejected orders') }}</span>
            <span class="flex items-center gap-1.5"><x-icon name="check" size="16" class="text-ok" />{{ __('Reseller group pricing') }}</span>
            <span class="flex items-center gap-1.5"><x-icon name="check" size="16" class="text-ok" />{{ __('2FA secured accounts') }}</span>
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-8 py-6 flex flex-col gap-5">
    <h2 class="m-0 text-[28px] font-bold">{{ __('Service categories') }}</h2>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(280px,1fr))]">
        @foreach ([
            ['IMEI / Serial Services', 'Carrier unlocks, NCK/MCK codes, IMEI & GSX checks. Submit IMEI, SN or ECID.', 'phone', 'bg-brand-soft text-brand-ink', 'imei'],
            ['Server · Box · Tool Credits', 'UnlockTool, Chimera, TFM, dongle renewals, game credits and gift cards.', 'server', 'bg-ok-soft text-ok', 'server'],
            ['Remote · Rent · Files', 'Live remote sessions, hourly tool rental and paid firmware files.', 'monitor', 'bg-point-soft text-point', 'remote'],
        ] as [$name, $desc, $icon, $tone, $type])
            <a href="{{ route('services.index', ['type' => $type]) }}" class="card p-6 flex flex-col gap-3 no-underline text-ink hover:text-ink">
                <span class="w-12 h-12 rounded-xl flex items-center justify-center {{ $tone }}"><x-icon :name="$icon" size="24" /></span>
                <span class="font-display text-xl font-bold">{{ __($name) }}</span>
                <span class="text-sm leading-normal text-muted">{{ __($desc) }}</span>
            </a>
        @endforeach
    </div>
</section>

@if ($hot->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 sm:px-8 py-8 flex flex-col gap-5">
    <h2 class="m-0 text-[28px] font-bold flex items-center gap-2.5"><x-icon name="fire" size="24" class="text-danger" />{{ __('Hot services') }}</h2>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(260px,1fr))]">
        @foreach ($hot as $service)
            <div class="card p-5 flex flex-col gap-2.5">
                <span class="text-xs font-semibold text-muted uppercase tracking-wide">{{ $service->category->name }}</span>
                <span class="text-base font-semibold leading-snug">{{ $service->name }}</span>
                <span class="flex items-center gap-1.5 text-[13px] text-muted"><x-icon name="clock" size="14" />{{ $service->delivery_time }}</span>
                <div class="flex justify-between items-center mt-1.5">
                    <span class="font-display text-xl font-bold">{{ price($service->priceFor(auth()->user())) }}</span>
                    <a href="{{ route('services.show', $service) }}" class="btn btn-primary h-10">{{ __('Order') }}</a>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

<section class="max-w-7xl mx-auto px-4 sm:px-8 py-8 flex flex-col gap-5">
    <h2 class="m-0 text-[28px] font-bold">{{ __('How it works') }}</h2>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
        @foreach ([
            ['Register & verify', 'Email OTP and optional Google Authenticator.'],
            ['Add funds', 'Transfer by mobile pay, upload the receipt, admin approves.'],
            ['Place orders', 'Single or bulk IMEI. Credit is held until the result arrives.'],
            ['Get result & points', 'Code delivered to your orders page; points added on success.'],
        ] as $i => [$h, $p])
            <div class="card p-6 flex flex-col gap-2">
                <span class="font-display text-[32px] font-bold text-brand-ink">{{ sprintf('%02d', $i + 1) }}</span>
                <span class="font-semibold text-[17px]">{{ __($h) }}</span>
                <span class="text-sm text-muted leading-normal">{{ __($p) }}</span>
            </div>
        @endforeach
    </div>
</section>

@if ($paymentMethods->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 sm:px-8 py-8">
    <div class="bg-brand text-white rounded-[18px] p-9 flex flex-wrap gap-6 items-center justify-between">
        <div class="flex-[1_1_400px] flex flex-col gap-2">
            <span class="font-display text-[26px] font-bold">{{ __('Pay the Myanmar way') }}</span>
            <span class="text-[15px] opacity-95">{{ __('Transfer, upload your receipt and your wallet is credited after approval.') }}</span>
        </div>
        <div class="flex flex-wrap gap-2.5">
            @foreach ($paymentMethods as $m)
                <span class="px-4 py-3 rounded-[10px] bg-white text-slate-900 font-bold text-sm">{{ $m->name }}</span>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
