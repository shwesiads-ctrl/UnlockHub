@extends('layouts.account', ['title' => 'API Access'])

@section('content')
<p class="m-0 text-muted text-[15px] max-w-3xl">{{ __('Connect your own DHRU Fusion site (or any script) to :site. Orders use your account group prices and are charged in the active service currency.', ['site' => setting('site.name', config('app.name'))]) }}</p>

@if ($newKey)
    <section class="rounded-2xl p-5 bg-ok-soft flex flex-col gap-2" role="alert">
        <span class="font-bold text-ok">{{ __('Your new API key — copy it now') }}</span>
        <div class="flex flex-wrap gap-2 items-center">
            <code class="flex-1 min-w-0 font-mono text-[15px] text-ink bg-surface px-3 py-2.5 rounded-lg break-all select-all">{{ $newKey }}</code>
            <button type="button" class="btn" data-copy="{{ $newKey }}">{{ __('Copy') }}</button>
        </div>
        <span class="text-xs text-ok">{{ __('For your security we only store a fingerprint of it. If you lose it, generate a new one.') }}</span>
    </section>
@endif

<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
        <section class="card p-6 flex flex-col gap-4">
            <div class="flex justify-between items-center gap-3">
                <h2 class="m-0 text-lg font-bold">{{ __('Connection details') }}</h2>
                @if ($user->api_enabled && $user->api_key_hash)
                    <span class="badge badge-success">{{ __('API on') }}</span>
                @else
                    <span class="badge badge-pending">{{ __('API off') }}</span>
                @endif
            </div>
            <dl class="m-0 grid gap-3 text-sm">
                @foreach ([
                    ['API URL', $apiUrl, true],
                    ['Username', $user->username, true],
                    ['API access key', $user->api_key_hash ? '••••-••••-••••-'.$user->api_key_hint : 'Not created yet', false],
                    ['API type', 'DHRU Fusion', false],
                ] as [$label, $value, $copy])
                    <div class="grid grid-cols-[9rem_1fr_auto] gap-3 items-center p-3 rounded-[10px] bg-surface-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="m-0 font-mono font-semibold break-all">{{ $value }}</dd>
                        @if ($copy) <button type="button" class="btn h-8 px-2.5 text-xs" data-copy="{{ $value }}">{{ __('Copy') }}</button> @else <span></span> @endif
                    </div>
                @endforeach
            </dl>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('account.api.generate') }}" @if ($user->api_key_hash) onsubmit="return confirm('Create a new key? The old key stops working immediately.')" @endif>
                    @csrf
                    <button class="btn btn-primary">{{ $user->api_key_hash ? 'Generate new key' : 'Create API key' }}</button>
                </form>
                @if ($user->api_key_hash)
                    <form method="POST" action="{{ route('account.api.revoke') }}" onsubmit="return confirm('Delete your API key?')">@csrf @method('DELETE')<button class="btn btn-danger">{{ __('Delete key') }}</button></form>
                @endif
            </div>
            @if ($user->api_last_used_at)
                <p class="m-0 text-xs text-muted">{{ __('Last used :time.', ['time' => $user->api_last_used_at->diffForHumans()]) }}</p>
            @endif
        </section>

        <section class="card overflow-hidden">
            <h2 class="m-0 px-5 py-4 text-lg font-bold">{{ __('Supported actions') }}</h2>
            <div class="table-wrap"><table class="table min-w-[560px]">
                <thead><tr><th>{{ __('action') }}</th><th>{{ __('parameters') }}</th><th>{{ __('returns') }}</th></tr></thead>
                <tbody>
                    <tr><td class="font-mono">{{ __('accountinfo') }}</td><td>—</td><td>{{ __('credit, mail, active currency') }}</td></tr>
                    <tr><td class="font-mono">{{ __('imeiservicelist') }}</td><td>—</td><td>{{ __('Groups → services with SERVICEID, CREDIT (your price), TIME, required fields') }}</td></tr>
                    <tr><td class="font-mono">{{ __('getimeiservicedetails') }}</td><td class="font-mono">ID</td><td>{{ __('One service') }}</td></tr>
                    <tr><td class="font-mono">{{ __('placeimeiorder') }}</td><td class="font-mono">{{ __('ID, IMEI / SN / …, CUSTOMFIELD (base64 JSON), REFERENCE') }}</td><td>{{ __('REFERENCEID (our order ID)') }}</td></tr>
                    <tr><td class="font-mono">{{ __('getimeiorder') }}</td><td class="font-mono">ID</td><td>{{ __('STATUS 0 pending · 1 in process · 3 rejected · 4 success, CODE, COMMENTS') }}</td></tr>
                </tbody>
            </table></div>
            <div class="px-5 pb-5 flex flex-col gap-2 text-sm">
                <span class="font-semibold">{{ __('Example (curl)') }}</span>
                <pre class="m-0 p-4 rounded-xl bg-surface-2 overflow-x-auto text-[12px] leading-relaxed"><code>curl -X POST {{ $endpoint }} \
  -d username={{ $user->username }} \
  -d apiaccesskey=YOUR-KEY \
  -d action=placeimeiorder \
  -d requestformat=JSON \
  --data-urlencode "parameters=&lt;PARAMETERS&gt;&lt;ID&gt;12&lt;/ID&gt;&lt;IMEI&gt;354612345678901&lt;/IMEI&gt;&lt;/PARAMETERS&gt;"</code></pre>
                <span class="text-xs text-muted">{{ __('Rejected orders are refunded automatically. Rate limit: 120 requests per minute.') }}</span>
            </div>
        </section>

        <section id="resellers" class="card p-6 flex flex-col gap-4 scroll-mt-24">
            <div>
                <span class="badge badge-info">{{ __('Resellers') }}</span>
                <h2 class="m-0 mt-2 text-xl font-bold">{{ __('Reseller developer API') }}</h2>
                <p class="m-0 mt-1 text-sm text-muted">{{ __('Connect a reseller account or DHRU-compatible shop to UnlockHub.') }}</p>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-xl bg-surface-2 p-4">
                    <h3 class="m-0 text-sm font-bold">{{ __('1. Create credentials') }}</h3>
                    <p class="m-0 mt-1 text-sm text-muted">{{ __('Create an API key on this page, then enable API access. Use your UnlockHub username (or email) and your own key. Never put the key in browser-side code or share it with customers.') }}</p>
                </div>
                <div class="rounded-xl bg-surface-2 p-4">
                    <h3 class="m-0 text-sm font-bold">{{ __('2. Connect your shop') }}</h3>
                    <p class="m-0 mt-1 text-sm text-muted">{{ __('Send POST requests to the API URL above at /api/index.php. The API processes orders for the authenticated account, using its group prices and active order currency.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table min-w-[620px]">
                    <thead><tr><th>{{ __('Setting') }}</th><th>{{ __('Value / behavior') }}</th></tr></thead>
                    <tbody>
                        <tr><td class="font-semibold">{{ __('Endpoint') }}</td><td><code>{{ $endpoint }}</code></td></tr>
                        <tr><td class="font-semibold">{{ __('Method and format') }}</td><td>POST · <code>requestformat=JSON</code></td></tr>
                        <tr><td class="font-semibold">{{ __('Authentication') }}</td><td><code>username</code> + <code>apiaccesskey</code></td></tr>
                        <tr><td class="font-semibold">{{ __('Charge and refund') }}</td><td>{{ __('Orders charge the active service currency (Ks or points). Rejected and cancelled orders are returned automatically in that currency.') }}</td></tr>
                        <tr><td class="font-semibold">{{ __('Retries') }}</td><td>{{ __('Send a unique REFERENCE with placeimeiorder. Retrying the same service and reference returns the original order instead of charging twice.') }}</td></tr>
                        <tr><td class="font-semibold">{{ __('Rate limit') }}</td><td>{{ __('120 requests per minute; failed authentication is separately limited.') }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="min-w-0">
                    <h3 class="m-0 mb-2 text-sm font-bold">{{ __('Place an order') }}</h3>
                    <pre class="m-0 p-4 rounded-xl bg-surface-2 overflow-x-auto text-[12px] leading-relaxed"><code>curl -X POST {{ $endpoint }} \
  -d username={{ $user->username }} \
  -d apiaccesskey=YOUR-KEY \
  -d action=placeimeiorder \
  -d requestformat=JSON \
  --data-urlencode "parameters=&lt;PARAMETERS&gt;&lt;ID&gt;12&lt;/ID&gt;&lt;IMEI&gt;354612345678901&lt;/IMEI&gt;&lt;REFERENCE&gt;shop-ord-1001&lt;/REFERENCE&gt;&lt;/PARAMETERS&gt;"</code></pre>
                </div>
                <div class="min-w-0">
                    <h3 class="m-0 mb-2 text-sm font-bold">{{ __('Check order status') }}</h3>
                    <pre class="m-0 p-4 rounded-xl bg-surface-2 overflow-x-auto text-[12px] leading-relaxed"><code>curl -X POST {{ $endpoint }} \
  -d username={{ $user->username }} \
  -d apiaccesskey=YOUR-KEY \
  -d action=getimeiorder \
  -d requestformat=JSON \
  --data-urlencode "parameters=&lt;PARAMETERS&gt;&lt;ID&gt;1452&lt;/ID&gt;&lt;/PARAMETERS&gt;"</code></pre>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2 text-sm">
                <div class="rounded-xl border border-line p-4">
                    <h3 class="m-0 text-sm font-bold">{{ __('Order status values') }}</h3>
                    <p class="m-0 mt-1 text-muted"><code>0</code> {{ __('Pending') }} · <code>1</code> {{ __('In process') }} · <code>3</code> {{ __('Rejected or cancelled') }} · <code>4</code> {{ __('Success') }}. Successful results are returned in <code>CODE</code>; rejection details are in <code>COMMENTS</code>.</p>
                </div>
                <div class="rounded-xl border border-line p-4">
                    <h3 class="m-0 text-sm font-bold">{{ __('Custom service fields') }}</h3>
                    <p class="m-0 mt-1 text-muted">{{ __('Send standard fields (such as IMEI or SN) as XML elements. For other required fields, send CUSTOMFIELD as base64-encoded JSON keyed by the field name or label.') }}</p>
                </div>
            </div>

            <div class="rounded-xl bg-surface-2 p-4 text-sm">
                <h3 class="m-0 text-sm font-bold">{{ __('Response format') }}</h3>
                <p class="m-0 mt-1 text-muted">{{ __('Responses are JSON with apiversion 6.1. Successes use SUCCESS; errors use ERROR with a MESSAGE. Service-list CREDIT and account credit use the active currency.') }}</p>
                <pre class="m-0 mt-3 p-3 rounded-lg bg-bg overflow-x-auto text-[12px]"><code>{
  "SUCCESS": [{ "MESSAGE": "Order received", "REFERENCEID": 1452 }],
  "apiversion": "6.1"
}</code></pre>
            </div>

            <p class="m-0 text-xs text-muted">{{ __('API keys can be restricted to allowed server IPs in Security. This API supports account info, service list/details, order placement and order status; it does not provide reseller customer-management endpoints.') }}</p>
        </section>
    </div>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <form method="POST" action="{{ route('account.api.update') }}" class="card p-5 flex flex-col gap-3.5">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">{{ __('Security') }}</h2>
            <label class="flex items-center gap-2.5 text-sm font-semibold">
                <input type="checkbox" name="api_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($user->api_enabled) @disabled(! $user->api_key_hash)>
                {{ __('API enabled') }}
            </label>
            <div>
                <label for="api_ips" class="label">{{ __('Allowed IPs') }} <span class="font-normal text-muted">{{ __('(one per line, empty = any)') }}</span></label>
                <textarea id="api_ips" name="api_ips" rows="4" class="input font-mono text-sm" placeholder="203.0.113.10">{{ old('api_ips', $user->api_ips) }}</textarea>
                <x-field-error name="api_ips" />
                <p class="m-0 mt-1 text-xs text-muted">{{ __('Strongly recommended: add your DHRU server\'s IP so a leaked key can\'t be used elsewhere.') }}</p>
            </div>
            <button class="btn btn-primary">{{ __('Save') }}</button>
        </form>

        <section class="card p-5 flex flex-col gap-2">
            <h2 class="m-0 text-lg font-bold">{{ __('Recent API orders') }}</h2>
            @forelse ($recent as $o)
                <a href="{{ route('account.orders.show', $o) }}" class="flex justify-between items-center gap-2 text-sm py-2 border-b border-line last:border-0 no-underline text-ink hover:text-brand-ink">
                    <span class="min-w-0"><span class="font-mono">#{{ $o->id }}</span> <span class="text-muted truncate">{{ \Illuminate\Support\Str::limit($o->service_name, 28) }}</span></span>
                    <x-status-badge :status="$o->status" />
                </a>
            @empty
                <p class="m-0 text-sm text-muted">{{ __('No API orders yet.') }}</p>
            @endforelse
        </section>
    </aside>
</div>
{{ $recent->links() }}
@endsection
