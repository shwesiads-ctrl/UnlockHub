@extends('layouts.account', ['title' => __('My customers')])

@section('content')
@include('reseller._nav')
<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 flex flex-col gap-3">
        <form method="GET" class="card p-3 flex gap-2"><label for="q" class="sr-only">{{ __('Search') }}</label><input id="q" name="q" value="{{ $q }}" class="input h-10 flex-1" placeholder="{{ __('Search name, username, email') }}"><button class="btn h-10">{{ __('Search') }}</button></form>
        <div class="card table-wrap">
            <table class="table min-w-[560px]">
                <thead><tr><th>{{ __('Customer') }}</th><th class="text-right">{{ __('Wallet') }}</th><th>{{ __('Open orders') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($customers as $c)
                    <tr>
                        <td><span class="font-semibold">{{ $c->name }}</span><div class="text-xs text-muted">{{ $c->username }}{{ $c->email ? ' · '.$c->email : '' }}</div></td>
                        <td class="text-right font-semibold">{{ money($c->balance) }}</td>
                        <td>{{ $c->open_orders }}</td>
                        <td>@if ($c->status === 'active') <span class="badge badge-success">{{ __('Active') }}</span> @else <span class="badge badge-rejected">{{ __('Suspended') }}</span> @endif</td>
                        <td class="text-right"><a href="{{ route('reseller.customers.show', $c) }}" class="btn h-9 text-xs">{{ __('Manage') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-8">{{ __('No customers yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $customers->links() }}
    </section>

    <form method="POST" action="{{ route('reseller.customers.store') }}" class="flex-[1_1_300px] min-w-0 card p-5 flex flex-col gap-3" data-submit-once autocomplete="off">
        @csrf
        <h2 class="m-0 text-lg font-bold">{{ __('Add a customer') }}</h2>
        <div><label for="c_name" class="label text-xs">{{ __('Name') }}</label><input id="c_name" name="name" value="{{ old('name') }}" class="input h-10" required><x-field-error name="name" /></div>
        <div><label for="c_user" class="label text-xs">{{ __('Username (to log in)') }}</label><input id="c_user" name="username" value="{{ old('username') }}" class="input h-10" required><x-field-error name="username" /></div>
        <div><label for="c_email" class="label text-xs">{{ __('Email') }} <span class="font-normal text-muted">({{ __('optional') }})</span></label><input id="c_email" name="email" type="email" value="{{ old('email') }}" class="input h-10"><x-field-error name="email" /></div>
        <div><label for="c_phone" class="label text-xs">{{ __('Phone') }} <span class="font-normal text-muted">({{ __('optional') }})</span></label><input id="c_phone" name="phone" value="{{ old('phone') }}" class="input h-10"></div>
        <div><label for="c_pass" class="label text-xs">{{ __('Password') }}</label><input id="c_pass" name="password" type="password" class="input h-10" required autocomplete="new-password"><x-field-error name="password" /></div>
        <div><label for="c_bal" class="label text-xs">{{ __('Opening balance from my wallet (Ks)') }}</label><input id="c_bal" name="opening_balance" type="number" min="0" step="1" value="{{ old('opening_balance') }}" class="input h-10"></div>
        <button class="btn btn-primary">{{ __('Create customer') }}</button>
        @if ($url = auth()->user()->resellerProfile?->storeUrl())
            <p class="m-0 text-xs text-muted">{{ __('Customers can also sign up themselves at') }} <a href="{{ $url }}/register" target="_blank" rel="noopener">{{ $url }}</a>.</p>
        @endif
    </form>
</div>
@endsection
