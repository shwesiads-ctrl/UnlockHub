@extends('layouts.admin', ['title' => 'Suppliers & API', 'subtitle' => 'DHRU Fusion connections that process API-mode services automatically'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <p class="m-0 text-sm text-muted flex-1">Balances refresh every hour. Orders are checked every minute while the scheduler runs.</p>
    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary h-10">+ Add supplier</a>
</div>

@if ($suppliers->isEmpty())
    <div class="card p-10 text-center flex flex-col gap-3 items-center">
        <span class="font-semibold">No suppliers yet.</span>
        <span class="text-sm text-muted max-w-lg">Add the DHRU Fusion site you buy from (API URL, username and API access key from that site's API settings). Then import its services with your profit margin.</span>
        <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">Add your first supplier</a>
    </div>
@else
    <div class="grid gap-5 grid-cols-[repeat(auto-fit,minmax(360px,1fr))]">
        @foreach ($suppliers as $s)
            <section class="card p-5 flex flex-col gap-4">
                <div class="flex justify-between items-start gap-3">
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <h2 class="m-0 text-lg font-bold truncate">{{ $s->name }}</h2>
                        <span class="text-xs text-muted truncate">{{ \App\Services\Suppliers\SupplierManager::DRIVERS[$s->driver]['label'] ?? $s->driver }} · {{ parse_url($s->api_url, PHP_URL_HOST) }}</span>
                    </div>
                    @php $tone = ['active' => 'success', 'disabled' => 'pending', 'error' => 'rejected'][$s->status] ?? 'pending'; @endphp
                    <span class="badge badge-{{ $tone }}">{{ $s->status === 'error' ? 'Error' : ucfirst($s->status) }}</span>
                </div>

                <div class="grid grid-cols-3 gap-3 text-sm p-3.5 rounded-xl bg-surface-2">
                    <div class="flex flex-col"><span class="text-xs text-muted">Balance</span><span class="font-display font-bold text-lg">{{ $s->balance !== null ? number_format((float) $s->balance, 2) : '—' }}</span><span class="text-xs text-muted">{{ $s->currency }}</span></div>
                    <div class="flex flex-col"><span class="text-xs text-muted">Services</span><span class="font-display font-bold text-lg">{{ $s->services_count }}</span></div>
                    <div class="flex flex-col"><span class="text-xs text-muted">Open orders</span><span class="font-display font-bold text-lg">{{ $s->open_orders_count }}</span></div>
                </div>

                <div class="text-xs text-muted">
                    1 {{ $s->currency }} = {{ number_format((float) $s->exchange_rate, 2) }} Ks
                    · checked {{ $s->last_synced_at?->diffForHumans() ?? 'never' }}
                </div>

                @if ($s->last_error)
                    <div class="rounded-[10px] px-3 py-2.5 bg-danger-soft text-danger text-[13px] leading-normal" role="alert">{{ $s->last_error }}</div>
                @endif

                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.suppliers.test', $s) }}">@csrf<button class="btn h-9 text-xs">Test &amp; refresh balance</button></form>
                    <a href="{{ route('admin.suppliers.services', $s) }}" class="btn btn-primary h-9 text-xs">Browse &amp; import services</a>
                    <form method="POST" action="{{ route('admin.suppliers.sync-costs', $s) }}">@csrf<button class="btn h-9 text-xs" @disabled($s->services_count === 0)>Sync costs</button></form>
                    <a href="{{ route('admin.suppliers.edit', $s) }}" class="btn h-9 text-xs ml-auto">Edit</a>
                </div>
            </section>
        @endforeach
    </div>
@endif
{{ $suppliers->links() }}
@endsection
