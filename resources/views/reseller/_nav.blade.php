<nav aria-label="{{ __('Reseller') }}" class="flex flex-wrap gap-2">
    @foreach (['reseller.dashboard' => 'Overview', 'reseller.customers' => 'Customers', 'reseller.orders' => 'Orders', 'reseller.prices' => 'My prices', 'reseller.branding' => 'Branding', 'account.earnings' => 'Earnings'] as $r => $l)
        <a href="{{ route($r) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => request()->routeIs($r) || ($r === 'reseller.customers' && request()->routeIs('reseller.customers.*'))])>{{ __($l) }}</a>
    @endforeach
    @if ($url = auth()->user()->resellerProfile?->storeUrl())
        <a href="{{ $url }}" target="_blank" rel="noopener" class="btn h-9 text-[13px] ml-auto">{{ __('My shop') }} ↗</a>
    @endif
</nav>
