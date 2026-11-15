@props(['form', 'provider' => null, 'siteKey' => null, 'force' => false])
{{-- Bot check widget. <x-captcha form="login" /> — renders nothing when it's off for this form. --}}
@php
    $provider ??= \App\Services\Captcha::provider();
    $siteKey ??= \App\Services\Captcha::siteKey();
    $show = $force ? ($provider !== 'none' && $siteKey) : \App\Services\Captcha::enabledFor($form);
@endphp
@if ($show)
    <div class="flex flex-col gap-1" data-captcha="{{ $provider }}" data-sitekey="{{ $siteKey }}" data-action="{{ $form }}">
        @if ($provider === 'turnstile')
            <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-action="{{ $form }}" data-theme="auto" data-size="flexible"></div>
            @once <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script> @endonce
        @elseif ($provider === 'recaptcha_v2')
            <div class="g-recaptcha" data-sitekey="{{ $siteKey }}"></div>
            @once <script src="https://www.google.com/recaptcha/api.js" async defer></script> @endonce
        @else
            <input type="hidden" name="g-recaptcha-response" value="">
            <p class="m-0 text-[11px] text-muted">{{ __('Protected by reCAPTCHA ·') }} <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">{{ __('Privacy') }}</a> · <a href="https://policies.google.com/terms" target="_blank" rel="noopener">{{ __('Terms') }}</a></p>
            @once <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($siteKey) }}" async defer></script> @endonce
        @endif
        <x-field-error name="captcha" />
    </div>
@endif
