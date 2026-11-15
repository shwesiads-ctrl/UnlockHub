@extends('layouts.account', ['title' => __('Favourites')])

@section('content')
<form method="GET" class="card p-4 flex gap-2">
    <label for="q" class="sr-only">{{ __('Search favourites') }}</label>
    <input id="q" name="q" value="{{ $q }}" class="input h-10 flex-1" placeholder="{{ __('Search favourites') }}">
    <button class="btn h-10">{{ __('Search') }}</button>
</form>

<section class="card table-wrap">
    <table class="table min-w-[620px]">
        <thead><tr><th>{{ __('Service') }}</th><th>{{ __('Delivery') }}</th><th class="text-right">{{ __('Price') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($services as $s)
            <tr>
                <td>
                    <div class="font-medium">{{ $s->name }}</div>
                    <div class="text-xs text-muted">{{ $s->category?->name }}</div>
                    @if ($s->status !== 'active') <span class="badge bg-surface-2 text-muted">{{ __('Not available now') }}</span>
                    @elseif ($s->sales_paused) <span class="badge badge-pending">{{ __('Temporarily unavailable') }}</span> @endif
                </td>
                <td class="text-muted whitespace-nowrap">{{ $s->delivery_time ?: '—' }}</td>
                <td class="text-right font-display font-bold whitespace-nowrap">{{ price($s->priceFor(auth()->user())) }}</td>
                <td class="text-right whitespace-nowrap">
                    @if ($s->status === 'active') <a href="{{ route('services.show', $s) }}" class="btn btn-primary h-9 text-xs">{{ __('Order') }}</a> @endif
                    <form method="POST" action="{{ route('account.favorites.toggle', $s) }}" class="inline">@csrf<button class="btn h-9 text-xs" aria-label="{{ __('Remove from favourites') }}">★ {{ __('Remove') }}</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-10">{{ $q !== '' ? __('No favourites match.') : __('No favourites yet. Tap ☆ next to a service.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
