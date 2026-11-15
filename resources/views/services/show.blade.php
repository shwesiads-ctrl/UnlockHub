@extends('layouts.app', ['title' => $service->name])

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-8 flex flex-col gap-5">
    <nav aria-label="Breadcrumb" class="text-sm text-muted flex flex-wrap gap-2">
        <a href="{{ route('services.index') }}">Services</a><span>/</span>
        <a href="{{ route('services.index', ['type' => $service->type]) }}">{{ \App\Models\Service::TYPES[$service->type] }}</a><span>/</span>
        <span class="text-ink">{{ $service->category->name }}</span>
    </nav>

    <div class="flex flex-wrap gap-6 items-start">
        <section class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
            <div class="card p-6 flex flex-col gap-3.5">
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-info">{{ \App\Models\Service::TYPES[$service->type] }}</span>
                    @if ($service->is_hot) <span class="badge badge-hot">HOT</span> @endif
                </div>
                <div class="flex items-start gap-3">
                    <h1 class="m-0 text-[30px] font-bold flex-1">{{ $service->name }}</h1>
                    @auth
                        <form method="POST" action="{{ route('account.favorites.toggle', $service) }}" data-favorite>
                            @csrf
                            <button class="btn h-10 {{ $isFavorite ? 'text-point' : '' }}" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"><span aria-hidden="true">{{ $isFavorite ? '★' : '☆' }}</span> <span data-fav-label>{{ $isFavorite ? __('Favourite') : __('Add to favourites') }}</span></button>
                        </form>
                    @endauth
                </div>
                <div class="flex flex-wrap gap-6 text-sm text-muted">
                    @if ($service->delivery_time)
                        <span class="flex items-center gap-1.5"><x-icon name="clock" size="16" />{{ $service->delivery_time }}</span>
                    @endif
                    @if ($points > 0)
                        <span class="flex items-center gap-1.5 text-point font-semibold"><x-icon name="star" size="16" />+{{ $points }} points per success</span>
                    @endif
                    <span>Price: <strong class="font-display text-ink text-base">{{ price($price) }}</strong>{{ $service->allow_bulk ? ' / item' : '' }}</span>
                </div>
                @if ($delivery && $delivery->reliable())
                    @php $hm = fn ($m) => \App\Services\DeliveryStatsService::human((int) $m); @endphp
                    <div class="rounded-xl bg-surface-2 px-4 py-3 text-sm flex flex-col gap-0.5">
                        <span><x-icon name="clock" size="14" /> {{ __('Recently most orders finished in') }} <strong>{{ $hm($delivery->p25_minutes) }} – {{ $hm($delivery->p75_minutes) }}</strong> ({{ __('median') }} {{ $hm($delivery->median_minutes) }}).</span>
                        <span class="text-xs text-muted">{{ __('Estimate from') }} {{ number_format($delivery->samples) }} {{ __('finished orders in the last 30 days, updated') }} {{ $delivery->computed_at->diffForHumans() }}. {{ __('Not a guarantee — times can change.') }}</span>
                    </div>
                @elseif ($delivery)
                    <p class="m-0 text-xs text-muted">{{ __('Not enough recent orders to estimate delivery time yet.') }}</p>
                @endif
                @if (($service->popup['enabled'] ?? false))
                    <button type="button" class="btn w-fit text-brand-ink" data-open-popup="service-popup"><x-icon name="file" size="16" />{{ __('Service info & rules') }}</button>
                @endif
                @if ($service->description)
                    <div class="text-sm leading-relaxed text-muted whitespace-pre-line">{{ $service->description }}</div>
                @endif
            </div>

            <form id="order" method="POST" action="{{ Route::has('orders.store') ? route('orders.store', $service) : '#' }}" class="card p-6 flex flex-col gap-4" data-submit-once>
                @csrf
                {{-- One token per form load: resending the same form never places the order twice --}}
                <input type="hidden" name="request_token" value="{{ old('request_token', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <h2 class="m-0 text-xl font-bold">{{ __('Order details') }}</h2>
                    @auth
                        @unless ($service->sales_paused)
                            <a href="{{ route('account.bulk.create', $service) }}" class="btn h-9 text-[13px]"><x-icon name="upload" size="16" />{{ __('Bulk from CSV / Excel') }}</a>
                        @endunless
                    @endauth
                </div>
                @forelse ($service->fields as $field)
                    <div>
                        <label for="f_{{ $field->name }}" class="label">
                            {{ $field->label }} @if ($field->is_required) <span class="text-danger">*</span> @endif
                            @if ($field->is_bulk_field && $service->allow_bulk)
                                <span class="font-normal text-muted">— one per line, max {{ $service->max_bulk }}</span>
                            @endif
                        </label>
                        @if ($field->is_bulk_field && $service->allow_bulk)
                            <textarea id="f_{{ $field->name }}" name="fields[{{ $field->name }}]" rows="5" class="input font-mono" placeholder="{{ $field->placeholder }}" @required($field->is_required)>{{ old('fields.'.$field->name) }}</textarea>
                        @elseif ($field->input_type === 'select')
                            <select id="f_{{ $field->name }}" name="fields[{{ $field->name }}]" class="input" @required($field->is_required)>
                                <option value="">Select…</option>
                                @foreach ($field->options ?? [] as $opt)
                                    <option @selected(old('fields.'.$field->name) === $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->input_type === 'textarea')
                            <textarea id="f_{{ $field->name }}" name="fields[{{ $field->name }}]" rows="3" class="input" placeholder="{{ $field->placeholder }}" @required($field->is_required)>{{ old('fields.'.$field->name) }}</textarea>
                        @else
                            <input id="f_{{ $field->name }}" name="fields[{{ $field->name }}]" type="{{ $field->input_type === 'email' ? 'email' : 'text' }}"
                                   @if ($field->validation === 'imei') inputmode="numeric" maxlength="15" @endif
                                   value="{{ old('fields.'.$field->name) }}" placeholder="{{ $field->placeholder }}" class="input" @required($field->is_required)>
                        @endif
                        <x-field-error :name="'fields.'.$field->name" />
                    </div>
                @empty
                    <p class="m-0 text-sm text-muted">No extra details needed for this service.</p>
                @endforelse
                <div>
                    <label for="user_note" class="label">Note to admin <span class="font-normal text-muted">(optional)</span></label>
                    <input id="user_note" name="user_note" type="text" maxlength="255" value="{{ old('user_note') }}" class="input">
                </div>
                <label class="flex items-start gap-2.5 text-sm text-muted leading-normal">
                    <input type="checkbox" name="confirm" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" required>
                    <span>{{ __('I checked the details. Wrong entries are non-refundable and In Process orders can\'t be cancelled.') }}</span>
                </label>
                <x-field-error name="confirm" />

                @if ($re = session('reorder'))
                    @php $balance = auth()->check() ? (float) auth()->user()->balance : 0; $total = (float) $price * max(1, $re['items']); @endphp
                    <div class="rounded-xl border border-brand/30 bg-brand-soft p-4 flex flex-col gap-1.5 text-sm" role="status">
                        <strong>{{ __('Ordering again from') }} #{{ $re['order_no'] }} — {{ __('check the details above, then place the order.') }}</strong>
                        <span>{{ __('Price now') }}: <strong>{{ price($price) }}</strong>
                            @if (abs((float) $price - $re['old_price']) >= 0.01) ({{ __('was') }} {{ price($re['old_price']) }}) @endif
                            @if ($re['items'] > 1) × {{ $re['items'] }} = <strong>{{ price($total) }}</strong> @endif</span>
                        <span @class(['text-danger font-semibold' => $balance < $total])>{{ __('Wallet') }}: {{ price($balance) }}{{ $balance < $total ? ' — '.__('not enough, add funds first') : '' }}</span>
                        <span class="text-xs text-muted">{{ __('Nothing is charged until you press Place order.') }}</span>
                    </div>
                @endif

                @if (session('duplicates'))
                    <div @class(['rounded-xl border p-4 flex flex-col gap-2 text-sm', 'bg-danger-soft border-danger/30' => session('duplicates_blocked'), 'bg-warn-soft border-warn/40' => ! session('duplicates_blocked')]) role="alert">
                        <strong @class(['text-danger' => session('duplicates_blocked'), 'text-warn' => ! session('duplicates_blocked')])>
                            {{ session('duplicates_blocked') ? __('You already have an open order for this — duplicates are not allowed for this service.') : __('You already have an open order for this:') }}
                        </strong>
                        <ul class="m-0 pl-5 list-disc">
                            @foreach (session('duplicates') as $d)
                                <li><span class="font-mono">{{ $d['imei'] }}</span> — <a href="{{ $d['url'] }}">#{{ $d['order_no'] }}</a> · {{ $d['status'] }} · {{ $d['date'] }}</li>
                            @endforeach
                        </ul>
                        @unless (session('duplicates_blocked'))
                            <label class="flex items-start gap-2.5 mt-1">
                                <input type="checkbox" name="confirm_duplicates" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]">
                                <span>{{ __('I know — place a new order anyway (it will be charged again).') }}</span>
                            </label>
                        @endunless
                    </div>
                @endif

                @if ($service->sales_paused)
                    <div class="badge-pending rounded-xl px-4 py-3 text-sm font-medium" role="status">{{ __('This service is temporarily unavailable. Please check back later.') }}</div>
                    <button class="btn btn-primary btn-lg opacity-60" disabled>{{ __('Temporarily unavailable') }}</button>
                @else
                @guest
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">{{ __('Log in to order') }}</a>
                @else
                    @if (Route::has('orders.store'))
                        <button class="btn btn-primary btn-lg">{{ __('Place order') }}</button>
                    @else
                        <button class="btn btn-primary btn-lg opacity-60" disabled>Ordering opens soon</button>
                    @endif
                @endguest
                @endif
            </form>
        </section>

        <aside class="flex-[1_1_340px] min-w-0 flex flex-col gap-4">
            <div class="card p-6 flex flex-col gap-3.5">
                <h2 class="m-0 text-xl font-bold">{{ __('Summary') }}</h2>
                <div class="flex justify-between text-sm"><span class="text-muted">Unit price</span><span class="font-semibold">{{ price($price) }}</span></div>
                @if ($points > 0)
                    <div class="flex justify-between text-sm"><span class="text-muted">Points if successful</span><span class="font-semibold text-point">+{{ $points }} pts</span></div>
                @endif
                @auth
                    <div class="flex justify-between text-sm p-3 rounded-[10px] bg-ok-soft text-ok font-semibold"><span>Wallet balance</span><span>{{ price(auth()->user()->balance) }}</span></div>
                @endauth
                <p class="m-0 text-xs text-muted leading-normal text-center">Credit is deducted when you order and refunded automatically if the order is rejected.</p>
            </div>
            <div class="card p-5 flex flex-col gap-2.5 text-sm">
                <span class="font-bold">Order status flow</span>
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge status="pending" /><span class="text-muted">→</span>
                    <x-status-badge status="in_process" /><span class="text-muted">→</span>
                    <x-status-badge status="success" /><span class="text-muted">/</span>
                    <span class="badge badge-rejected">Rejected · Refunded</span>
                </div>
            </div>
        </aside>
    </div>
</div>

@if ($service->popup['enabled'] ?? false)
    @php $sp = \App\Support\Popup::sanitize($service->popup); @endphp
    <x-popup :popup="$sp" id="service-popup" :title="$service->name" :delivery="$service->delivery_time" :auto="$sp['trigger'] === 'open'" />
@endif
@endsection
