@php
    $siteName = setting('site.name', config('app.name'));
    $logo = setting('site.logo');
    $showName = setting('site.logo_show_name', '1') === '1';
@endphp
<span class="flex items-center gap-2.5">
    @if ($logo)
        <img src="{{ Storage::disk('public')->url($logo) }}" alt="{{ $showName ? '' : $siteName }}" class="h-9 w-auto max-w-[160px] object-contain">
    @else
        <span class="w-9 h-9 rounded-[9px] bg-brand text-white flex items-center justify-center" aria-hidden="true"><x-icon name="lock" size="20" /></span>
    @endif
    @if ($showName || ! $logo)
        <span class="font-display text-xl font-bold">{{ $siteName }}</span>
    @endif
</span>
