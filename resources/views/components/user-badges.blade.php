@props(['user', 'size' => 'md', 'staff' => false])
{{--
    Group badge (Customer / VIP / Reseller… — colour + icon set in Admin › Groups & Pricing),
    plus an "Authorized reseller" badge for approved resellers and an optional Staff badge.
--}}
@php
    $group = $user->isAdmin() ? null : ($user->relationLoaded('group') ? $user->group : $user->group()->first());
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $group?->badge_color) ? $group->badge_color : '#64748B';
    $icon = in_array($group?->badge_icon, ['user', 'star', 'crown', 'gem', 'shield', 'bolt'], true) ? $group->badge_icon : 'user';
    $resellerProfile = $user->isAdmin() ? null : ($user->relationLoaded('resellerProfile') ? $user->resellerProfile : $user->resellerProfile()->first());
    $isReseller = ! $user->isAdmin() && $resellerProfile?->status === 'approved';
    $isVerifiedReseller = $isReseller && (bool) $resellerProfile?->is_verified;
    $cls = $size === 'sm' ? 'text-[11px] px-2 py-0.5 gap-1' : 'text-xs px-2.5 py-1 gap-1.5';
    $ic = $size === 'sm' ? 12 : 14;
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-1.5 align-middle']) }}>
    @if ($user->isAdmin())
        <span class="inline-flex items-center rounded-full font-bold whitespace-nowrap bg-brand-soft text-brand-ink border border-brand/30 {{ $cls }}" title="{{ __('Administrator account') }}"><x-icon name="shield" :size="$ic" />{{ __('Administrator') }}</span>
    @else
        <span class="inline-flex items-center rounded-full font-bold whitespace-nowrap {{ $cls }}"
              style="color: {{ $color }}; background: color-mix(in srgb, {{ $color }} 14%, transparent); border: 1px solid color-mix(in srgb, {{ $color }} 35%, transparent)"
              title="{{ __('Price group') }}">
            <x-icon :name="$icon" :size="$ic" />{{ $group?->name ?? __('Customer') }}
        </span>
        @if ($isReseller && strcasecmp((string) $group?->name, 'Reseller') !== 0)
            <span class="inline-flex items-center rounded-full font-bold whitespace-nowrap bg-brand-soft text-brand-ink border border-brand/30 {{ $cls }}" title="{{ __('Approved reseller') }}"><x-icon name="shield" :size="$ic" />{{ __('Reseller') }}</span>
        @endif
        @if ($isVerifiedReseller)
            <span class="inline-flex items-center rounded-full font-bold whitespace-nowrap bg-ok-soft text-ok {{ $cls }}" title="{{ __('Verified reseller') }}"><x-icon name="check" :size="$ic" />{{ __('Verified') }}</span>
        @endif
    @endif
    @if ($staff && $user->isAdmin())
        <span class="inline-flex items-center rounded-full font-bold whitespace-nowrap bg-surface-2 text-ink {{ $cls }}"><x-icon name="lock" :size="$ic" />{{ \App\Support\Permissions::ROLES[$user->staffRole()] ?? 'Staff' }}</span>
    @endif
</span>
