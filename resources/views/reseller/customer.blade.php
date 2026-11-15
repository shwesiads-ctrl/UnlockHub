@extends('layouts.account', ['title' => $customer->name])

@section('content')
@include('reseller._nav')
<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 flex flex-col gap-4">
        <div class="card p-5 flex flex-wrap gap-6 items-center">
            <div class="flex-1 min-w-48"><div class="font-bold text-lg">{{ $customer->name }}</div><div class="text-sm text-muted">{{ $customer->username }}{{ $customer->email ? ' · '.$customer->email : '' }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</div></div>
            <div class="text-right"><div class="text-xs text-muted">{{ __('Wallet') }}</div><div class="font-display text-2xl font-bold">{{ money($customer->balance) }}</div></div>
        </div>
        <div class="card table-wrap">
            <table class="table min-w-[560px]">
                <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Service') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Your profit') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td class="font-mono text-[13px]">#{{ $o->order_no }}<div class="text-xs text-muted">{{ $o->created_at->format('d M H:i') }}</div></td>
                        <td>{{ \Illuminate\Support\Str::limit($o->service_name, 40) }}<div class="text-xs text-muted font-mono">{{ $o->imei }}</div></td>
                        <td class="text-right">{{ $o->priceLabel() }}</td>
                        <td class="text-right text-ok">{{ $o->reseller_price !== null ? money(((float) $o->price - (float) $o->reseller_price) * (($o->price_currency ?? 'ks') === 'points' ? max(1, (int) ($o->price_rate ?: 1)) : 1)) : '—' }}</td>
                        <td><x-status-badge :status="$o->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-8">{{ __('No orders yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </section>

    <aside class="flex-[1_1_300px] min-w-0 flex flex-col gap-4">
        <form method="POST" action="{{ route('reseller.customers.transfer', $customer) }}" class="card p-5 flex flex-col gap-3" data-submit-once>
            @csrf
            <h2 class="m-0 text-lg font-bold">{{ __('Wallet') }}</h2>
            <div class="grid grid-cols-[7rem_1fr] gap-2">
                <div><label for="dir" class="label text-xs">{{ __('Action') }}</label><select id="dir" name="direction" class="input h-10"><option value="give">{{ __('Add') }}</option><option value="take">{{ __('Take back') }}</option></select></div>
                <div><label for="amt" class="label text-xs">{{ __('Amount (Ks)') }}</label><input id="amt" name="amount" type="number" min="1" step="1" class="input h-10" required></div>
            </div>
            <div><label for="note" class="label text-xs">{{ __('Note') }}</label><input id="note" name="note" maxlength="80" class="input h-10" placeholder="{{ __('e.g. KBZPay received') }}"></div>
            <p class="m-0 text-xs text-muted">{{ __('Adding moves money from your wallet') }} ({{ money(auth()->user()->balance) }}).</p>
            <button class="btn btn-primary">{{ __('Apply') }}</button>
        </form>

        <form method="POST" action="{{ route('reseller.customers.update', $customer) }}" class="card p-5 flex flex-col gap-3" autocomplete="off">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">{{ __('Account') }}</h2>
            <div><label for="st" class="label text-xs">{{ __('Status') }}</label><select id="st" name="status" class="input h-10"><option value="active" @selected($customer->status === 'active')>{{ __('Active') }}</option><option value="suspended" @selected($customer->status === 'suspended')>{{ __('Suspended') }}</option></select></div>
            <div><label for="pw" class="label text-xs">{{ __('New password') }} <span class="font-normal text-muted">({{ __('optional') }})</span></label><input id="pw" name="password" type="password" class="input h-10" autocomplete="new-password"><x-field-error name="password" /></div>
            <button class="btn">{{ __('Save') }}</button>
        </form>

        <section class="card p-5 flex flex-col gap-2 text-[13px]">
            <h2 class="m-0 text-base font-bold">{{ __('Recent wallet activity') }}</h2>
            @forelse ($transactions as $t)
                <div class="flex justify-between gap-2"><span class="text-muted">{{ $t->created_at->format('d M H:i') }}</span><span class="truncate">{{ \Illuminate\Support\Str::limit($t->description, 28) }}</span><span @class(['font-semibold', 'text-ok' => $t->amount > 0, 'text-danger' => $t->amount < 0])>{{ $t->amount > 0 ? '+' : '−' }}{{ money(abs($t->amount)) }}</span></div>
            @empty
                <span class="text-muted">—</span>
            @endforelse
        </section>
    </aside>
</div>
@endsection
