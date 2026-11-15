@extends('layouts.account', ['title' => __('Branding')])

@section('content')
@include('reseller._nav')
@php $base = setting('reseller.base_domain') ?: parse_url(config('app.url'), PHP_URL_HOST); $wl = setting('reseller.whitelabel', '1') === '1'; @endphp
<form method="POST" action="{{ route('reseller.branding.update') }}" enctype="multipart/form-data" class="card p-6 flex flex-col gap-4 max-w-3xl">
    @csrf @method('PUT')
    <p class="m-0 text-sm text-muted">{{ __('Your customers see your name, logo, colour and contacts instead of ours.') }}</p>
    <div class="flex items-center gap-4">
        @if ($profile->logoUrl()) <img src="{{ $profile->logoUrl() }}" alt="" class="h-14 max-w-48 object-contain rounded-lg bg-surface-2 p-1"> @endif
        <div class="flex-1"><label for="logo" class="label text-xs">{{ __('Logo (PNG/JPG/WebP, max 1 MB)') }}</label><input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="input py-2"><x-field-error name="logo" /></div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label for="shop_name" class="label">{{ __('Shop name') }}</label><input id="shop_name" name="shop_name" value="{{ old('shop_name', $profile->shop_name) }}" class="input" required maxlength="80"></div>
        <div><label for="brand_color" class="label">{{ __('Brand colour') }}</label><input id="brand_color" name="brand_color" type="color" value="{{ old('brand_color', $profile->brand_color ?: '#2459E8') }}" class="input p-1"></div>
    </div>
    @if ($wl)
        <div>
            <label for="subdomain" class="label">{{ __('Shop address') }}</label>
            <div class="flex items-center gap-2"><input id="subdomain" name="subdomain" value="{{ old('subdomain', $profile->subdomain) }}" class="input" placeholder="{{ __('myshop') }}" maxlength="40"><span class="text-sm text-muted whitespace-nowrap">.{{ $base }}</span></div>
            <x-field-error name="subdomain" />
            @if ($profile->custom_domain) <p class="m-0 mt-1 text-xs text-muted">{{ __('Custom domain') }}: <strong>{{ $profile->custom_domain }}</strong> ({{ __('set by admin') }})</p> @endif
            @if ($url = $profile->storeUrl()) <p class="m-0 mt-1 text-xs"><a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></p> @endif
        </div>
    @endif
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label for="cp" class="label text-xs">{{ __('Phone / Viber') }}</label><input id="cp" name="contact_phone" value="{{ old('contact_phone', $profile->contact_phone) }}" class="input h-10"></div>
        <div><label for="ct" class="label text-xs">{{ __('Telegram') }}</label><input id="ct" name="contact_telegram" value="{{ old('contact_telegram', $profile->contact_telegram) }}" class="input h-10" placeholder="@myshop"></div>
        <div><label for="ce" class="label text-xs">{{ __('Email') }}</label><input id="ce" name="contact_email" type="email" value="{{ old('contact_email', $profile->contact_email) }}" class="input h-10"></div>
    </div>
    <div><label for="about" class="label">{{ __('About your shop') }} <span class="font-normal text-muted">({{ __('shown on your home page') }})</span></label><textarea id="about" name="about" rows="3" maxlength="2000" class="input">{{ old('about', $profile->about) }}</textarea></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_signup" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($profile->allow_signup)>{{ __('Let customers register themselves on my shop address') }}</label>
    <button class="btn btn-primary self-start">{{ __('Save branding') }}</button>
</form>
@endsection
