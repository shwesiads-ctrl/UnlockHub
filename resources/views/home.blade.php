@extends('layouts.app')

@section('content')
@php
    $homeCtaUser = auth()->user();
    $showResellerCta = setting('home.reseller_cta_enabled', '1') === '1'
        && \App\Services\ResellerService::enabled()
        && (! $homeCtaUser || (! $homeCtaUser->isAdmin() && ! $homeCtaUser->parent_id && ! $homeCtaUser->canAccessResellerPanel()));
    $homeText = fn (string $key, string $default = '') => setting('home.'.$key, $default) ?? $default;
    $showCategories = setting('home.show_categories', '1') === '1';
    $showHotServices = setting('home.show_hot_services', '1') === '1';
    $showHowItWorks = setting('home.show_how_it_works', '1') === '1';
    $showPaymentBanner = setting('home.show_payment_banner', '1') === '1';
    $trustDefaults = ['trust_1' => 'Auto refund on rejected orders', 'trust_2' => 'Reseller group pricing', 'trust_3' => '2FA secured accounts'];
    $stepDefaults = [
        1 => ['Register & verify', 'Email OTP and optional Google Authenticator.'],
        2 => ['Add funds', 'Transfer by mobile pay, upload the receipt, admin approves.'],
        3 => ['Place orders', 'Single or bulk IMEI. Credit is held until the result arrives.'],
        4 => ['Get result & points', 'Code delivered to your orders page; points added on success.'],
    ];
@endphp
<section class="max-w-7xl mx-auto px-4 sm:px-8 pt-16 pb-12 flex flex-wrap gap-12 items-center">
    <div class="flex-[1_1_520px] min-w-0 flex flex-col gap-5">
        @if ($homeText('hero_badge', 'For repair shops & resellers in Myanmar')) <span class="self-start badge badge-info text-[13px]">{{ __($homeText('hero_badge', 'For repair shops & resellers in Myanmar')) }}</span> @endif
        <h1 class="m-0 text-4xl sm:text-[56px] leading-[1.05] font-bold tracking-tight">{{ __($homeText('hero_title', 'Unlock codes, tool credits & activations — in one wallet.')) }}</h1>
        <p class="m-0 text-lg leading-relaxed text-muted max-w-xl">
            {{ __($homeText('hero_description', 'Top up with :methods, place IMEI and server orders, track every result, and earn points on each successful order.'), ['methods' => $paymentMethods->take(3)->pluck('name')->join(', ') ?: __('local payment methods')]) }}
        </p>
        <form role="search" action="{{ route('services.index') }}" class="flex flex-wrap gap-2 max-w-xl">
            <label for="hsearch" class="sr-only">{{ __('Search services') }}</label>
            <div class="search-box flex-[1_1_320px] flex items-center gap-2.5 h-[52px] px-4 rounded-xl border border-line bg-surface text-muted">
                <x-icon name="search" />
                <input id="hsearch" name="q" type="text" placeholder="{{ __('Search: Samsung NCK, UnlockTool, AT&T…') }}" class="flex-1 bg-transparent outline-none text-ink text-[15px]">
            </div>
            <button class="btn btn-primary btn-lg">{{ __($homeText('browse_label', 'Browse services')) }}</button>
        </form>
        @if ($showResellerCta)
            <a href="{{ $homeCtaUser ? route('account.reseller.apply') : route('register') }}" class="btn h-11 self-start">{{ __($homeText('reseller_cta_label', 'Become a reseller')) }}</a>
        @endif
        <div class="flex flex-wrap gap-5 text-sm text-muted">
            @foreach (['trust_1', 'trust_2', 'trust_3'] as $trust)
                @if ($homeText($trust, $trustDefaults[$trust])) <span class="flex items-center gap-1.5"><x-icon name="check" size="16" class="text-ok" />{{ __($homeText($trust, $trustDefaults[$trust])) }}</span> @endif
            @endforeach
        </div>
    </div>
</section>

@if ($showCategories)
<section class="max-w-7xl mx-auto px-4 sm:px-8 py-6 flex flex-col gap-5">
    <h2 class="m-0 text-[28px] font-bold">{{ __($homeText('categories_title', 'Service categories')) }}</h2>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(280px,1fr))]">
        @foreach ([
            [$homeText('category_imei_title', 'IMEI / Serial Services'), $homeText('category_imei_description', 'Carrier unlocks, NCK/MCK codes, IMEI & GSX checks. Submit IMEI, SN or ECID.'), 'phone', 'bg-brand-soft text-brand-ink', 'imei'],
            [$homeText('category_server_title', 'Server · Box · Tool Credits'), $homeText('category_server_description', 'UnlockTool, Chimera, TFM, dongle renewals, game credits and gift cards.'), 'server', 'bg-ok-soft text-ok', 'server'],
            [$homeText('category_remote_title', 'Remote · Rent · Files'), $homeText('category_remote_description', 'Live remote sessions, hourly tool rental and paid firmware files.'), 'monitor', 'bg-point-soft text-point', 'remote'],
        ] as [$name, $desc, $icon, $tone, $type])
            <a href="{{ route('services.index', ['type' => $type]) }}" class="card p-6 flex flex-col gap-3 no-underline text-ink hover:text-ink">
                <span class="w-12 h-12 rounded-xl flex items-center justify-center {{ $tone }}"><x-icon :name="$icon" size="24" /></span>
                <span class="font-display text-xl font-bold">{{ $name }}</span>
                <span class="text-sm leading-normal text-muted">{{ $desc }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif

@if ($showHotServices && $hot->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 sm:px-8 py-8 flex flex-col gap-5">
    <h2 class="m-0 text-[28px] font-bold flex items-center gap-2.5"><x-icon name="fire" size="24" class="text-danger" />{{ __($homeText('hot_title', 'Hot services')) }}</h2>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(260px,1fr))]">
        @foreach ($hot as $service)
            <div class="card p-5 flex flex-col gap-2.5">
                <span class="text-xs font-semibold text-muted uppercase tracking-wide">{{ $service->category->name }}</span>
                <span class="text-base font-semibold leading-snug">{{ $service->name }}</span>
                <span class="flex items-center gap-1.5 text-[13px] text-muted"><x-icon name="clock" size="14" />{{ $service->delivery_time }}</span>
                <div class="flex justify-between items-center mt-1.5">
                    <span class="font-display text-xl font-bold">{{ $service->formattedPriceFor(auth()->user()) }}</span>
                    <a href="{{ route('services.show', $service) }}" class="btn btn-primary h-10">{{ __('Order') }}</a>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

@if ($showHowItWorks)
<section class="max-w-7xl mx-auto px-4 sm:px-8 py-8 flex flex-col gap-5">
    <h2 class="m-0 text-[28px] font-bold">{{ __($homeText('how_title', 'How it works')) }}</h2>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
        @foreach ([1, 2, 3, 4] as $i)
            <div class="card p-6 flex flex-col gap-2">
                <span class="font-display text-[32px] font-bold text-brand-ink">{{ sprintf('%02d', $i) }}</span>
                <span class="font-semibold text-[17px]">{{ __($homeText('step_'.$i.'_title', $stepDefaults[$i][0])) }}</span>
                <span class="text-sm text-muted leading-normal">{{ __($homeText('step_'.$i.'_description', $stepDefaults[$i][1])) }}</span>
            </div>
        @endforeach
    </div>
</section>
@endif

@if ($showPaymentBanner && ($paymentMethods->isNotEmpty() || $payCountries->isNotEmpty()))
<section class="max-w-7xl mx-auto px-4 sm:px-8 py-8" id="pay">
    <div class="bg-brand text-white rounded-[18px] p-9 flex flex-col gap-5">
        <div class="flex flex-wrap gap-6 items-center justify-between">
            <div class="flex-[1_1_400px] flex flex-col gap-2">
                <span class="font-display text-[26px] font-bold">
                    {{ filled($homeText('payment_title')) ? __($homeText('payment_title'), ['country' => __(config('unlockhub.countries.'.$payCountry))]) : ($payCountry === 'MM' ? __('Pay the Myanmar way') : __('Pay with local wallets in :country', ['country' => __(config('unlockhub.countries.'.$payCountry))]) ) }}
                </span>
                <span class="text-[15px] opacity-95">{{ __($homeText('payment_description', 'Transfer, upload your receipt and your wallet is credited after approval.')) }}</span>
            </div>
            <div class="flex flex-wrap gap-2.5">
                @forelse ($paymentMethods as $m)
                    <span class="px-4 py-3 rounded-[10px] bg-white text-slate-900 font-bold text-sm">
                        {{ $m->name }}@if ($m->isForeign()) <span class="font-normal text-slate-500">· {{ $m->currency }}</span>@endif
                    </span>
                @empty
                    <span class="text-[15px] opacity-95">{{ __('No local payment methods for this country yet.') }}</span>
                @endforelse
            </div>
        </div>
        @if ($payCountries->count() > 1 || ($payCountries->isNotEmpty() && ! $payCountries->has($payCountry)))
            <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="{{ __('Payment country') }}">
                <span class="opacity-90">{{ __('Paying from') }}:</span>
                @foreach ($payCountries as $code => $label)
                    <a href="{{ route('home', ['country' => $code]) }}#pay" @class([
                        'px-3 py-1.5 rounded-full no-underline font-semibold',
                        'bg-white text-slate-900' => $code === $payCountry,
                        'bg-white/15 text-white hover:text-white' => $code !== $payCountry,
                    ]) @if ($code === $payCountry) aria-current="true" @endif>{{ __($label) }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</section>
@endif
@endsection
