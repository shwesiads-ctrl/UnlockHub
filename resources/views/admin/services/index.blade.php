@extends('layouts.admin', ['title' => 'Services', 'subtitle' => 'Prices, order fields and API / Manual processing'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <form method="GET" class="flex flex-wrap gap-2 items-center flex-1">
        <label for="q" class="sr-only">Search</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Search name…" class="input h-10 w-56">
        <label for="type" class="sr-only">Type</label>
        <select id="type" name="type" class="input h-10 w-auto">
            <option value="">All types</option>
            @foreach (\App\Models\Service::TYPES as $k => $l) <option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option> @endforeach
        </select>
        <label for="category" class="sr-only">Group</label>
        <select id="category" name="category" class="input h-10 w-auto">
            <option value="">All groups</option>
            @foreach ($categories as $c) <option value="{{ $c->id }}" @selected((int) request('category') === $c->id)>{{ $c->name }}</option> @endforeach
        </select>
        <label for="status" class="sr-only">Status</label>
        <select id="status" name="status" class="input h-10 w-auto">
            <option value="">Any status</option>
            @foreach (['active', 'hidden', 'disabled'] as $s) <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option> @endforeach
        </select>
        <label for="mode" class="sr-only">Mode</label>
        <select id="mode" name="mode" class="input h-10 w-auto">
            <option value="">API &amp; Manual</option>
            <option value="api" @selected(request('mode') === 'api')>API</option>
            <option value="manual" @selected(request('mode') === 'manual')>Manual</option>
        </select>
        <button class="btn h-10">Filter</button>
    </form>
    <a href="{{ route('admin.categories.index') }}" class="btn h-10">Manage groups</a>
    <a href="{{ route('admin.services.create') }}" class="btn btn-primary h-10">+ New service</a>
</div>

<section class="card table-wrap">
    <table class="table min-w-[900px]">
        <thead>
            <tr><th>Service</th><th>Group</th><th>Mode</th><th class="text-right">Cost</th><th class="text-right">Price</th><th>Orders</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr>
        </thead>
        <tbody>
        @forelse ($services as $service)
            <tr>
                <td>
                    <a href="{{ route('admin.services.edit', $service) }}" class="font-semibold text-ink no-underline hover:text-brand-ink">{{ $service->name }}</a>
                    @if ($service->is_hot) <span class="badge badge-hot text-[11px] ml-1">HOT</span> @endif
                    <div class="text-xs text-muted">{{ \App\Models\Service::TYPES[$service->type] }} · {{ $service->delivery_time ?: '—' }}</div>
                </td>
                <td>{{ $service->category?->name }}</td>
                <td>
                    <x-status-badge :status="$service->processing_mode" />
                    @if ($service->processing_mode === 'api') <div class="text-xs text-muted mt-1">{{ $service->supplier?->name ?? 'No supplier' }}</div> @endif
                </td>
                <td class="text-right whitespace-nowrap text-muted">{{ money($service->cost_price) }}</td>
                <td class="text-right whitespace-nowrap font-semibold">{{ money($service->default_price) }}</td>
                <td>{{ $service->orders_count }}</td>
                <td>
                    @php $tone = ['active' => 'success', 'hidden' => 'pending', 'disabled' => 'rejected'][$service->status]; @endphp
                    <span class="badge badge-{{ $tone }}">{{ ucfirst($service->status) }}</span>
                </td>
                <td class="whitespace-nowrap text-right">
                    <form method="POST" action="{{ route('admin.services.toggle', $service) }}" class="inline">
                        @csrf
                        <button class="btn h-9 text-xs">{{ $service->status === 'active' ? 'Hide' : 'Activate' }}</button>
                    </form>
                    <a href="{{ route('admin.services.edit', $service) }}" class="btn h-9 text-xs">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-muted text-center py-10">No services match. <a href="{{ route('admin.services.create') }}">Create one</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

{{ $services->links() }}
@endsection
