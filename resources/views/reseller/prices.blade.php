@extends('layouts.account', ['title' => __('My prices')])

@section('content')
@include('reseller._nav')
@php $p = $me->resellerProfile; @endphp
<form method="GET" class="card p-3 flex gap-2"><label for="q" class="sr-only">{{ __('Search') }}</label><input id="q" name="q" value="{{ $q }}" class="input h-10 flex-1" placeholder="{{ __('Search services') }}"><button class="btn h-10">{{ __('Search') }}</button></form>

<form method="POST" action="{{ route('reseller.prices.update') }}" class="flex flex-col gap-4">
    @csrf @method('PUT')
    <section class="card p-5 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-60">
            <h2 class="m-0 text-lg font-bold">{{ __('Default markup') }}</h2>
            <p class="m-0 text-sm text-muted">{{ __('Used for every service without its own price below. Your customers pay your cost + this.') }}</p>
        </div>
        <div><label for="mv" class="label text-xs">{{ __('Markup') }}</label><input id="mv" name="markup_value" type="number" min="0" step="0.01" value="{{ (float) $p->markup_value }}" class="input h-10 w-28"></div>
        <div><label for="mt" class="label text-xs">{{ __('Type') }}</label><select id="mt" name="markup_type" class="input h-10 w-24"><option value="percent" @selected($p->markup_type === 'percent')>%</option><option value="fixed" @selected($p->markup_type === 'fixed')>{{ __('Ks') }}</option></select></div>
        <button class="btn btn-primary h-10">{{ __('Save prices') }}</button>
    </section>

    <section class="card table-wrap">
        <table class="table min-w-[720px]">
            <thead><tr><th>{{ __('Service') }}</th><th class="text-right">{{ __('Your cost') }}</th><th class="text-right">{{ __('Customers pay') }}</th><th class="w-40">{{ __('Own price (Ks)') }}</th><th class="text-right">{{ __('Profit') }}</th></tr></thead>
            <tbody>
            @foreach ($services as $s)
                @php $cost = \App\Services\ResellerService::costFor($s, $me); $sell = \App\Services\ResellerService::sellPrice($s, $me); @endphp
                <tr>
                    <td><span class="font-medium">{{ \Illuminate\Support\Str::limit($s->name, 60) }}</span><div class="text-xs text-muted">{{ $s->category?->name }}</div></td>
                    <td class="text-right whitespace-nowrap">{{ money($cost) }}</td>
                    <td class="text-right whitespace-nowrap font-semibold">{{ money($sell) }}</td>
                    <td><label for="p{{ $s->id }}" class="sr-only">{{ __('Own price') }}</label><input id="p{{ $s->id }}" name="prices[{{ $s->id }}]" type="number" min="0" step="1" value="{{ isset($own[$s->id]) ? (float) $own[$s->id] : '' }}" placeholder="{{ __('auto') }}" class="input h-9"></td>
                    <td class="text-right text-ok whitespace-nowrap">{{ money($sell - $cost) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    {{ $services->links() }}
    <button class="btn btn-primary self-start">{{ __('Save prices') }}</button>
</form>
@endsection
