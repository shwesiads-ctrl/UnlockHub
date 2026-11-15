@extends('layouts.account', ['title' => 'API Access'])

@section('content')
<p class="m-0 text-muted text-[15px] max-w-3xl">Connect your own DHRU Fusion site (or any script) to {{ setting('site.name', config('app.name')) }}. Orders placed through the API use your wallet balance and your group prices, exactly like orders on this site.</p>

@if ($newKey)
    <section class="rounded-2xl p-5 bg-ok-soft flex flex-col gap-2" role="alert">
        <span class="font-bold text-ok">Your new API key — copy it now</span>
        <div class="flex flex-wrap gap-2 items-center">
            <code class="flex-1 min-w-0 font-mono text-[15px] text-ink bg-surface px-3 py-2.5 rounded-lg break-all select-all">{{ $newKey }}</code>
            <button type="button" class="btn" data-copy="{{ $newKey }}">Copy</button>
        </div>
        <span class="text-xs text-ok">For your security we only store a fingerprint of it. If you lose it, generate a new one.</span>
    </section>
@endif

<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
        <section class="card p-6 flex flex-col gap-4">
            <div class="flex justify-between items-center gap-3">
                <h2 class="m-0 text-lg font-bold">Connection details</h2>
                @if ($user->api_enabled && $user->api_key_hash)
                    <span class="badge badge-success">API on</span>
                @else
                    <span class="badge badge-pending">API off</span>
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
                        @if ($copy) <button type="button" class="btn h-8 px-2.5 text-xs" data-copy="{{ $value }}">Copy</button> @else <span></span> @endif
                    </div>
                @endforeach
            </dl>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('account.api.generate') }}" @if ($user->api_key_hash) onsubmit="return confirm('Create a new key? The old key stops working immediately.')" @endif>
                    @csrf
                    <button class="btn btn-primary">{{ $user->api_key_hash ? 'Generate new key' : 'Create API key' }}</button>
                </form>
                @if ($user->api_key_hash)
                    <form method="POST" action="{{ route('account.api.revoke') }}" onsubmit="return confirm('Delete your API key?')">@csrf @method('DELETE')<button class="btn btn-danger">Delete key</button></form>
                @endif
            </div>
            @if ($user->api_last_used_at)
                <p class="m-0 text-xs text-muted">Last used {{ $user->api_last_used_at->diffForHumans() }}.</p>
            @endif
        </section>

        <section class="card overflow-hidden">
            <h2 class="m-0 px-5 py-4 text-lg font-bold">Supported actions</h2>
            <div class="table-wrap"><table class="table min-w-[560px]">
                <thead><tr><th>action</th><th>parameters</th><th>returns</th></tr></thead>
                <tbody>
                    <tr><td class="font-mono">accountinfo</td><td>—</td><td>credit (MMK), mail, currency</td></tr>
                    <tr><td class="font-mono">imeiservicelist</td><td>—</td><td>Groups → services with SERVICEID, CREDIT (your price), TIME, required fields</td></tr>
                    <tr><td class="font-mono">getimeiservicedetails</td><td class="font-mono">ID</td><td>One service</td></tr>
                    <tr><td class="font-mono">placeimeiorder</td><td class="font-mono">ID, IMEI / SN / …, CUSTOMFIELD (base64 JSON), REFERENCE</td><td>REFERENCEID (our order ID)</td></tr>
                    <tr><td class="font-mono">getimeiorder</td><td class="font-mono">ID</td><td>STATUS 0 pending · 1 in process · 3 rejected · 4 success, CODE, COMMENTS</td></tr>
                </tbody>
            </table></div>
            <div class="px-5 pb-5 flex flex-col gap-2 text-sm">
                <span class="font-semibold">Example (curl)</span>
                <pre class="m-0 p-4 rounded-xl bg-surface-2 overflow-x-auto text-[12px] leading-relaxed"><code>curl -X POST {{ $endpoint }} \
  -d username={{ $user->username }} \
  -d apiaccesskey=YOUR-KEY \
  -d action=placeimeiorder \
  -d requestformat=JSON \
  --data-urlencode "parameters=&lt;PARAMETERS&gt;&lt;ID&gt;12&lt;/ID&gt;&lt;IMEI&gt;354612345678901&lt;/IMEI&gt;&lt;/PARAMETERS&gt;"</code></pre>
                <span class="text-xs text-muted">Rejected orders are refunded automatically. Rate limit: 120 requests per minute.</span>
            </div>
        </section>
    </div>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <form method="POST" action="{{ route('account.api.update') }}" class="card p-5 flex flex-col gap-3.5">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">Security</h2>
            <label class="flex items-center gap-2.5 text-sm font-semibold">
                <input type="checkbox" name="api_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($user->api_enabled) @disabled(! $user->api_key_hash)>
                API enabled
            </label>
            <div>
                <label for="api_ips" class="label">Allowed IPs <span class="font-normal text-muted">(one per line, empty = any)</span></label>
                <textarea id="api_ips" name="api_ips" rows="4" class="input font-mono text-sm" placeholder="203.0.113.10">{{ old('api_ips', $user->api_ips) }}</textarea>
                <x-field-error name="api_ips" />
                <p class="m-0 mt-1 text-xs text-muted">Strongly recommended: add your DHRU server's IP so a leaked key can't be used elsewhere.</p>
            </div>
            <button class="btn btn-primary">Save</button>
        </form>

        <section class="card p-5 flex flex-col gap-2">
            <h2 class="m-0 text-lg font-bold">Recent API orders</h2>
            @forelse ($recent as $o)
                <a href="{{ route('account.orders.show', $o) }}" class="flex justify-between items-center gap-2 text-sm py-2 border-b border-line last:border-0 no-underline text-ink hover:text-brand-ink">
                    <span class="min-w-0"><span class="font-mono">#{{ $o->id }}</span> <span class="text-muted truncate">{{ \Illuminate\Support\Str::limit($o->service_name, 28) }}</span></span>
                    <x-status-badge :status="$o->status" />
                </a>
            @empty
                <p class="m-0 text-sm text-muted">No API orders yet.</p>
            @endforelse
        </section>
    </aside>
</div>
@endsection
