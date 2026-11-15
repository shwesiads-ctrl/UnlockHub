@php
    $locales = config('unlockhub.locales', ['en' => 'English']);
    $current = app()->getLocale();
@endphp
<details class="relative" data-locale-switcher>
    <summary class="btn text-[13px] list-none cursor-pointer" aria-label="{{ __('Language') }}">
        <x-icon name="globe" size="16" />
        <span lang="{{ $current }}" class="hidden sm:inline">{{ $locales[$current] ?? strtoupper($current) }}</span>
        <span class="sm:hidden uppercase">{{ $current }}</span>
    </summary>
    <ul class="absolute right-0 z-50 mt-1 min-w-40 max-w-[calc(100vw-24px)] rounded-xl border border-line bg-surface p-1 shadow-lg list-none m-0">
        @foreach ($locales as $code => $label)
            <li>
                <a href="{{ route('locale', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                   @class(['flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm no-underline text-ink hover:bg-surface-2', 'font-bold' => $code === $current])
                   @if ($code === $current) aria-current="true" @endif>
                    {{ $label }}
                    @if ($code === $current) <span aria-hidden="true">✓</span> @endif
                </a>
            </li>
        @endforeach
    </ul>
</details>
