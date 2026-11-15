@extends('layouts.app', ['title' => 'Services & Pricing'])

@section('content')
@php $pointsOn = \App\Services\PointService::on('order'); @endphp
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-8 flex flex-col gap-5">
    <div class="flex flex-col gap-1.5">
        <h1 class="m-0 text-[34px] font-bold">{{ __('Services & Reseller Pricing') }}</h1>
        <p class="m-0 text-muted text-[15px]">
            @auth
                Prices shown for your group: <strong class="text-ink">{{ auth()->user()->group?->name ?? 'Customer' }}</strong>.
            @else
                Showing standard prices. <a href="{{ route('login') }}">Log in</a> to see your reseller price.
            @endauth
            Delivery times are estimates.
        </p>
    </div>

    <nav aria-label="Service type" class="flex flex-wrap gap-2">
        @foreach (\App\Models\Service::TYPES as $key => $label)
            <a href="{{ route('services.index', ['type' => $key]) }}" @class([
                'btn',
                'btn-primary' => $type === $key,
            ]) @if ($type === $key) aria-current="page" @endif>{{ __($label) }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('services.index') }}" class="card p-3.5 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="flex-[1_1_300px] flex items-center gap-2.5 h-11 px-3.5 rounded-[10px] border border-line bg-bg text-muted">
            <x-icon name="search" />
            <label for="q" class="sr-only">Search services</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="{{ __('Search services…') }}" class="flex-1 bg-transparent outline-none text-ink text-sm">
        </div>
        <label for="category" class="sr-only">Group</label>
        <select id="category" name="category" class="input h-11 w-auto min-w-52" onchange="this.form.submit()">
            <option value="">{{ __('All groups') }}</option>
            @foreach ($allCategories as $cat)
                <option value="{{ $cat->id }}" @selected($categoryId === $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <label @class(['btn cursor-pointer', 'bg-danger-soft text-danger border-danger' => $hotOnly])>
            <input type="checkbox" name="hot" value="1" class="sr-only" @checked($hotOnly) onchange="this.form.submit()">
            <x-icon name="fire" size="16" /> {{ __('HOT only') }}
        </label>
        <button class="btn btn-primary">{{ __('Search') }}</button>
    </form>

    @if ($categories->isEmpty())
        <div class="card p-10 text-center flex flex-col gap-2 items-center">
            <span class="font-semibold">{{ __('No services found.') }}</span>
            @if ($otherTabHits)
                <span class="text-sm text-muted">
                    Found in:
                    @foreach ($otherTabHits as $t => $n)
                        <a href="{{ route('services.index', ['type' => $t, 'q' => $search]) }}">{{ \App\Models\Service::TYPES[$t] }} ({{ $n }})</a>@if (! $loop->last), @endif
                    @endforeach
                </span>
            @elseif ($search || $hotOnly || $categoryId)
                <a href="{{ route('services.index', ['type' => $type]) }}" class="text-sm">Clear filters</a>
            @endif
        </div>
    @else
        <div class="card table-wrap">
            <table class="table min-w-[760px]">
                <thead>
                    <tr>
                        <th>{{ __('Service') }}</th>
                        <th>{{ __('Delivery time') }}</th>
                        @if ($pointsOn) <th>{{ __('Points') }}</th> @endif
                        <th class="text-right">{{ __('Price') }}</th>
                        <th><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td colspan="5" class="bg-bg font-bold text-[13px] text-brand-ink">{{ $category->name }}</td>
                    </tr>
                    @foreach ($category->services as $service)
                        @php $p = $service->priceFor(auth()->user()); @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-2 font-medium">
                                    <a href="{{ route('services.show', $service) }}" class="text-ink hover:text-brand-ink no-underline">{{ $service->name }}</a>
                                    @if ($service->is_hot) <span class="badge badge-hot text-[11px]">HOT</span> @endif
                                </div>
                                @if ($service->fields->isNotEmpty())
                                    <div class="text-xs text-muted mt-1">Requires: {{ $service->fields->pluck('label')->join(' · ') }}</div>
                                @endif
                            </td>
                            <td class="text-muted whitespace-nowrap">{{ $service->delivery_time ?: '—' }}</td>
                            @if ($pointsOn) <td class="text-point font-semibold whitespace-nowrap">+{{ $service->pointsFor($p) }}</td> @endif
                            <td class="text-right font-display font-bold text-[15px] whitespace-nowrap">{{ price($p) }}</td>
                            <td class="text-right"><a href="{{ route('services.show', $service) }}" class="btn btn-primary h-10">{{ __('Order') }}</a></td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
    {{ $servicePage->links() }}
</div>
@endsection
