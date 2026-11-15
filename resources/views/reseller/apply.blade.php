@extends('layouts.account', ['title' => __('Become a reseller')])

@section('content')
@php $status = $profile?->status; @endphp
<section class="card p-6 flex flex-col gap-3">
    <h2 class="m-0 text-xl font-bold">{{ __('Sell to your own customers') }}</h2>
    <ul class="m-0 pl-5 text-sm leading-relaxed text-muted">
        <li>{{ __('Create customer accounts and fund their wallets from yours.') }}</li>
        <li>{{ __('Set your own prices (markup or per service) — the difference is your profit, added when an order succeeds.') }}</li>
        <li>{{ __('Your shop name, logo and colours; your own shop address') }}@if (setting('reseller.whitelabel', '1') === '1') (<code>yourshop.{{ setting('reseller.base_domain') ?: parse_url(config('app.url'), PHP_URL_HOST) }}</code>)@endif.</li>
        <li>{{ __('Withdraw your profits.') }}</li>
    </ul>
</section>

@if ($user->parent_id)
    <div class="badge-pending rounded-xl px-4 py-3 text-sm">{{ __('Your account belongs to a reseller, so it cannot become a reseller.') }}</div>
@elseif ($status === 'pending')
    <div class="badge-info rounded-xl px-4 py-3 text-sm">{{ __('Your application for') }} <strong>{{ $profile->shop_name }}</strong> {{ __('is waiting for review.') }}</div>
@elseif ($status === 'suspended')
    <div class="badge-rejected rounded-xl px-4 py-3 text-sm">{{ __('Your reseller account is suspended. Please contact support.') }}</div>
@elseif (! \App\Services\ResellerService::enabled())
    <div class="badge-pending rounded-xl px-4 py-3 text-sm">{{ __('The reseller program is closed right now.') }}</div>
@else
    @if ($status === 'rejected')
        <div class="badge-rejected rounded-xl px-4 py-3 text-sm">{{ __('Your last application was not approved.') }} {{ $profile->admin_note }}</div>
    @endif
    <form method="POST" action="{{ route('account.reseller.submit') }}" class="card p-6 flex flex-col gap-4 max-w-2xl" data-submit-once>
        @csrf
        <div><label for="shop_name" class="label">{{ __('Shop name') }}</label><input id="shop_name" name="shop_name" value="{{ old('shop_name', $profile?->shop_name ?? $user->shop_name) }}" class="input" required maxlength="80"><x-field-error name="shop_name" /></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="contact_phone" class="label">{{ __('Phone / Viber') }}</label><input id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $profile?->contact_phone ?? $user->phone) }}" class="input"></div>
            <div><label for="contact_telegram" class="label">Telegram</label><input id="contact_telegram" name="contact_telegram" value="{{ old('contact_telegram', $profile?->contact_telegram) }}" class="input" placeholder="@yourshop"></div>
        </div>
        <div><label for="application_note" class="label">{{ __('Tell us about your business') }} <span class="font-normal text-muted">({{ __('optional') }})</span></label><textarea id="application_note" name="application_note" rows="3" maxlength="500" class="input">{{ old('application_note') }}</textarea></div>
        <button class="btn btn-primary self-start">{{ __('Apply') }}</button>
    </form>
@endif
@endsection
