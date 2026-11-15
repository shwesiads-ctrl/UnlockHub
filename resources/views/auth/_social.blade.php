@unless (app()->bound('reseller.shop_by_domain') && app('reseller.shop_by_domain'))
@php $socialEnabled = \App\Services\Social\SocialProviders::enabled(); @endphp
@if ($socialEnabled)
    <div class="flex items-center gap-3 text-xs text-muted" aria-hidden="true"><span class="flex-1 h-px bg-line"></span>{{ __('or continue with') }}<span class="flex-1 h-px bg-line"></span></div>
    <div class="grid gap-2 grid-cols-2">
        @foreach (array_diff($socialEnabled, ['telegram']) as $provider)
            <a href="{{ route('social.redirect', $provider) }}" class="btn w-full">
                {{ \App\Services\Social\SocialProviders::PROVIDERS[$provider]['label'] }}
            </a>
        @endforeach
    </div>
    @if (in_array('telegram', $socialEnabled, true))
        <form method="POST" action="{{ route('social.telegram.start') }}" class="flex justify-center">@csrf<button class="btn w-full">Continue with Telegram</button></form>
    @endif
@endif
@endunless
