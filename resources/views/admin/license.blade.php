@extends('layouts.admin', ['title' => 'License', 'subtitle' => 'Serial key and domain lock for this site'])

@section('content')
@php
    $d = $status['data'];
    $tone = $status['valid'] ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-danger';
@endphp

@if (! $enabled)
    <div class="rounded-xl px-4 py-3 text-sm bg-warn-soft text-warn" role="alert">
        <strong>Domain lock is OFF.</strong> <code>config/license.php</code> has no <code>public_key</code> yet.
        Open your license server › Settings, copy the public key into that file, then give the script to buyers.
    </div>
@endif

<section class="card p-6 flex flex-col gap-4 max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="m-0 text-lg font-bold flex items-center gap-2"><x-icon name="shield" />Status</h2>
        <span class="badge {{ $tone }} text-sm px-3 py-1.5">{{ $status['valid'] ? ($status['reason'] === 'active' ? 'Licensed' : ($status['reason'] === 'dev' ? 'Local test — no key needed' : 'Lock off')) : 'Not licensed' }}</span>
    </div>
    @if ($status['message'])
        <p class="m-0 text-sm {{ $status['valid'] ? 'text-muted' : 'text-danger font-semibold' }}" role="{{ $status['valid'] ? 'status' : 'alert' }}">{{ $status['message'] }}</p>
    @endif
    <dl class="m-0 grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))] text-sm">
        <div><dt class="text-muted">This site</dt><dd class="m-0 font-semibold">{{ $host ?: '—' }} <span class="text-xs text-muted font-normal">(APP_URL)</span></dd></div>
        <div><dt class="text-muted">Serial key</dt><dd class="m-0 font-mono">{{ $serial ? \Illuminate\Support\Str::mask($serial, '•', 5, max(0, strlen($serial) - 9)) : '—' }}</dd></div>
        <div><dt class="text-muted">Licensed domain</dt><dd class="m-0 font-semibold">{{ $d['domain'] ?? '—' }}</dd></div>
        <div><dt class="text-muted">Customer / plan</dt><dd class="m-0">{{ $d['customer'] ?? '—' }}{{ ! empty($d['plan']) ? ' · '.$d['plan'] : '' }}</dd></div>
        <div><dt class="text-muted">Expires</dt><dd class="m-0">{{ ! empty($d['expires_at']) ? substr($d['expires_at'], 0, 10) : ($d ? 'Lifetime' : '—') }}</dd></div>
        <div><dt class="text-muted">Last check</dt><dd class="m-0">{{ $checkedAt ? \Illuminate\Support\Carbon::parse($checkedAt)->diffForHumans() : (isset($d['issued_at']) ? \Illuminate\Support\Carbon::createFromTimestamp($d['issued_at'])->diffForHumans() : '—') }}</dd></div>
    </dl>
    @if ($serial)
        <form method="POST" action="{{ route('admin.license.check') }}">@csrf<button class="btn">Check now</button></form>
    @endif
</section>

<form method="POST" action="{{ route('admin.license.activate') }}" class="card p-6 flex flex-col gap-4 max-w-3xl">
    @csrf
    <h2 class="m-0 text-lg font-bold">{{ $serial ? 'Change serial key' : 'Activate' }}</h2>
    <div>
        <label for="serial" class="label">Serial key</label>
        <input id="serial" name="serial" value="{{ old('serial') }}" class="input font-mono uppercase" placeholder="UHUB-XXXX-XXXX-XXXX-XXXX" autocomplete="off" required>
        <x-field-error name="serial" />
    </div>
    <p class="m-0 text-xs text-muted">One key works on one domain: <strong>{{ $host }}</strong>, its <strong>www.</strong> and subdomains (reseller shops). Make sure <code>APP_URL</code> in <code>.env</code> is your real address before activating. Local test addresses (localhost, *.test) never need a key.</p>
    <button class="btn btn-primary self-start">Activate</button>
</form>

@if ($serial && $status['valid'] && $status['reason'] === 'active')
    <form method="POST" action="{{ route('admin.license.deactivate') }}" class="card p-6 flex flex-col gap-3 max-w-3xl" onsubmit="return confirm('Release the key from this domain? The site locks until it is activated again.')">
        @csrf
        <h2 class="m-0 text-lg font-bold">Moving to a new domain?</h2>
        <p class="m-0 text-sm text-muted">Release the key here first, then activate it on the new domain. This site stops working until it is activated again.</p>
        <div>
            <label for="confirm" class="label">Type <span class="font-mono">{{ $host }}</span> to confirm</label>
            <input id="confirm" name="confirm" class="input" autocomplete="off" required>
            <x-field-error name="confirm" />
        </div>
        <button class="btn btn-danger self-start">Release key</button>
    </form>
@endif
<p class="text-xs text-muted">License server: {{ $server }}</p>
@endsection
