@props(['user', 'size' => 44])
@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="" width="{{ $size }}" height="{{ $size }}" {{ $attributes->merge(['class' => 'rounded-full object-cover shrink-0 bg-surface-2']) }} style="width: {{ $size }}px; height: {{ $size }}px">
@else
    <span {{ $attributes->merge(['class' => 'rounded-full bg-brand text-white font-bold flex items-center justify-center shrink-0']) }} style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ max(12, (int) ($size * 0.36)) }}px" aria-hidden="true">{{ $user->initials() }}</span>
@endif
