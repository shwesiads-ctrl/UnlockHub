@extends('layouts.admin', [
    'title' => $supplier->exists ? $supplier->name : 'Add supplier',
    'subtitle' => 'Suppliers & API / '.($supplier->exists ? 'Edit' : 'Create'),
])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <form method="POST" action="{{ $supplier->exists ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}" class="flex-[999_1_520px] min-w-0 card p-6 flex flex-col gap-4" novalidate autocomplete="off">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif

        <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
            <div>
                <label for="name" class="label">Name</label>
                <input id="name" name="name" value="{{ old('name', $supplier->name) }}" class="input" placeholder="e.g. GSM Supplier X" required>
                <x-field-error name="name" />
            </div>
            <div>
                <label for="driver" class="label">API type</label>
                <select id="driver" name="driver" class="input">
                    @foreach (\App\Services\Suppliers\SupplierManager::DRIVERS as $key => $d)
                        <option value="{{ $key }}" @selected(old('driver', $supplier->driver) === $key)>{{ $d['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-full">
                <label for="api_url" class="label">API URL</label>
                <input id="api_url" name="api_url" type="url" value="{{ old('api_url', $supplier->api_url) }}" class="input font-mono" placeholder="https://supplier-site.com" required>
                <p class="m-0 mt-1.5 text-xs text-muted">The site address is enough — <code>/api/index.php</code> is added automatically.</p>
                <x-field-error name="api_url" />
            </div>
            <div>
                <label for="api_username" class="label">API username</label>
                <input id="api_username" name="api_username" value="{{ old('api_username', $supplier->api_username) }}" class="input" required>
                <x-field-error name="api_username" />
            </div>
            <div>
                <label for="api_key" class="label">API access key</label>
                <input id="api_key" name="api_key" type="password" class="input font-mono" autocomplete="new-password"
                       placeholder="{{ $supplier->exists ? 'Leave empty to keep the saved key' : '' }}" @required(! $supplier->exists)>
                <p class="m-0 mt-1.5 text-xs text-muted">Stored encrypted. Never shown again.</p>
                <x-field-error name="api_key" />
            </div>
            <div>
                <label for="currency" class="label">Supplier currency</label>
                <input id="currency" name="currency" value="{{ old('currency', $supplier->currency) }}" maxlength="3" class="input uppercase" required>
                <x-field-error name="currency" />
            </div>
            <div>
                <label for="exchange_rate" class="label">1 unit = ? Ks</label>
                <input id="exchange_rate" name="exchange_rate" type="number" step="0.0001" min="0" value="{{ old('exchange_rate', (float) $supplier->exchange_rate) }}" class="input" required>
                <p class="m-0 mt-1.5 text-xs text-muted">Used to turn supplier credit into your Ks cost price.</p>
                <x-field-error name="exchange_rate" />
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <option value="active" @selected(old('status', $supplier->status) !== 'disabled')>Active</option>
                    <option value="disabled" @selected(old('status', $supplier->status) === 'disabled')>Disabled</option>
                </select>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('admin.suppliers.index') }}" class="btn">Cancel</a>
            <button class="btn btn-primary">Save &amp; test connection</button>
        </div>
    </form>

    <aside class="flex-[1_1_320px] min-w-0 card p-5 flex flex-col gap-3 text-sm leading-relaxed">
        <h2 class="m-0 text-lg font-bold">Where to find these</h2>
        <p class="m-0 text-muted">Log in to the supplier's DHRU site → <strong class="text-ink">API / Settings</strong>. Copy the API access key and username shown there.</p>
        <p class="m-0 text-muted">Many suppliers only allow requests from IPs you register. Add your server's IP on their site, or the connection test will fail with "Authentication Failed".</p>
        <p class="m-0 text-muted">After saving, use <strong class="text-ink">Browse &amp; import services</strong> to copy their services with your profit margin.</p>
        @if ($supplier->exists)
            <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" class="mt-3" onsubmit="return confirm('Delete this supplier? Suppliers with services are disabled instead.')">
                @csrf @method('DELETE')
                <button class="btn btn-danger h-9 text-xs">Delete supplier</button>
            </form>
        @endif
    </aside>
</div>
@endsection
