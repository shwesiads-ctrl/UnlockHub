@extends('layouts.account', ['title' => 'Add Funds'])

@section('content')
<p class="m-0 text-muted text-[15px]">Transfer to our account, then upload the receipt. Your wallet is credited when admin approves ({{ setting('site.support_hours', '9 AM – 10 PM') }}).</p>

@if ($methods->isEmpty())
    <div class="card p-8 text-center text-muted">Top-ups are not available right now. Please contact support.</div>
@else
<div class="flex flex-wrap gap-6 items-start">
    <section class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
        @php $gateways = \App\Services\Payments\OnlinePaymentService::gateways(); $oc = \App\Services\Payments\OnlinePaymentService::config(); @endphp
        @if ($gateways)
            <form method="POST" action="{{ route('account.online.store') }}" class="card p-6 flex flex-col gap-4" data-submit-once data-online-topup data-rate="{{ $oc['rate'] }}" data-fee="{{ $oc['fee_percent'] }}">
                @csrf
                <div>
                    <h2 class="m-0 text-xl font-bold">{{ __('Pay online — instant') }}</h2>
                    <p class="m-0 text-sm text-muted">{{ __('Card or PayPal. Your wallet is credited automatically as soon as the payment is confirmed.') }}</p>
                </div>
                <div role="radiogroup" aria-label="{{ __('Pay with') }}" class="grid gap-3 grid-cols-2">
                    @foreach ($gateways as $i => $g)
                        <label class="cursor-pointer rounded-xl border p-4 flex items-center gap-2 font-bold has-[:checked]:border-2 has-[:checked]:border-brand has-[:checked]:bg-brand-soft border-line">
                            <input type="radio" name="gateway" value="{{ $g }}" class="accent-[var(--uh-brand)]" @checked($i === 0)>{{ $g === 'stripe' ? '💳 '.__('Card') : 'PayPal' }}
                        </label>
                    @endforeach
                </div>
                <div>
                    <label for="op_amount" class="label">{{ __('Amount') }} ({{ $oc['currency'] }})</label>
                    <input id="op_amount" name="amount" type="number" step="0.01" min="{{ $oc['min'] }}" max="{{ $oc['max'] }}" value="{{ old('amount') }}" class="input" required inputmode="decimal">
                    <p class="m-0 mt-1.5 text-sm">{{ __('You get') }}: <strong data-online-ks>—</strong>
                        @if ($oc['fee_percent'] > 0) <span class="text-muted">· {{ __('fee') }} {{ $oc['fee_percent'] }}% · {{ __('you pay') }} <span data-online-total>—</span> {{ $oc['currency'] }}</span> @endif</p>
                    <p class="m-0 text-xs text-muted">1 {{ $oc['currency'] }} = {{ number_format($oc['rate'], 2) }} Ks · {{ $oc['min'] }}–{{ $oc['max'] }} {{ $oc['currency'] }}</p>
                </div>
                <button class="btn btn-primary btn-lg">{{ __('Continue to payment') }}</button>
            </form>
            <p class="m-0 text-center text-sm text-muted">{{ __('— or pay by mobile wallet and upload the receipt —') }}</p>
        @endif
        {{-- 1. Choose method --}}
        <div class="card p-6 flex flex-col gap-4">
            <h2 class="m-0 text-xl font-bold">1 · Choose payment method</h2>
            <nav aria-label="Payment method" class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(150px,1fr))]">
                @foreach ($methods as $m)
                    <a href="{{ route('account.funds.index', ['method' => $m->code]) }}" @class([
                        'min-h-[76px] p-3 rounded-xl flex flex-col justify-center gap-1 no-underline',
                        'border-2 border-brand bg-brand-soft text-brand-ink' => $selected->is($m),
                        'border border-line bg-bg text-ink hover:text-ink' => ! $selected->is($m),
                    ]) @if ($selected->is($m)) aria-current="true" @endif>
                        <span class="font-bold text-base">{{ $m->name }}</span>
                        <span class="text-xs opacity-85">Min {{ money($m->min_amount) }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="flex flex-wrap gap-5 p-5 rounded-xl bg-surface-2 items-center">
                @if ($selected->qr_path)
                    <img src="{{ Storage::disk('public')->url($selected->qr_path) }}" alt="{{ $selected->name }} QR code" class="w-36 h-36 rounded-xl bg-white p-2 object-contain">
                @endif
                <div class="flex-[1_1_240px] flex flex-col gap-2.5 text-sm">
                    <div class="flex justify-between gap-3"><span class="text-muted">Account name</span><span class="font-semibold">{{ $selected->account_name ?: '—' }}</span></div>
                    <div class="flex justify-between gap-3 items-center">
                        <span class="text-muted">{{ $selected->name }} number</span>
                        <span class="flex gap-2 items-center">
                            <span class="font-display font-bold text-base">{{ $selected->account_number ?: '—' }}</span>
                            @if ($selected->account_number)
                                <button type="button" class="btn h-8 px-2.5 text-xs" data-copy="{{ $selected->account_number }}">Copy</button>
                            @endif
                        </span>
                    </div>
                    @if ($selected->instructions)
                        <div class="px-3 py-2.5 rounded-lg bg-warn-soft text-warn text-[13px] font-medium leading-normal whitespace-pre-line">{{ str_replace(':username', auth()->user()->username, $selected->instructions) }}</div>
                    @endif
                    <div class="text-[13px] text-muted">Write your username <strong class="text-ink">{{ auth()->user()->username }}</strong> in the transfer note.</div>
                </div>
            </div>
        </div>

        {{-- 2. Upload receipt --}}
        <form method="POST" action="{{ route('account.funds.store') }}" enctype="multipart/form-data" data-submit-once class="card p-6 flex flex-col gap-4" novalidate>
            @csrf
            <input type="hidden" name="method" value="{{ $selected->code }}">
            <h2 class="m-0 text-xl font-bold">2 · Upload receipt</h2>
            <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                <div>
                    <label for="amount" class="label">Amount transferred (Ks) <span class="text-danger">*</span></label>
                    <input id="amount" name="amount" type="number" min="{{ (float) $selected->min_amount }}" step="1" inputmode="numeric" value="{{ old('amount') }}" class="input" placeholder="Min {{ number_format($selected->min_amount) }}" required data-amount>
                    <x-field-error name="amount" />
                </div>
                <div>
                    <label for="transaction_id" class="label">Transaction ID <span class="text-danger">*</span></label>
                    <input id="transaction_id" name="transaction_id" type="text" value="{{ old('transaction_id') }}" class="input font-mono" placeholder="From your payment app" required>
                    <x-field-error name="transaction_id" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Quick amounts">
                @foreach ([10000, 50000, 100000, 500000] as $quick)
                    @if ($quick >= $selected->min_amount && (! $selected->max_amount || $quick <= $selected->max_amount))
                        <button type="button" class="btn h-9 rounded-full text-[13px]" data-fill="#amount" data-value="{{ $quick }}">{{ number_format($quick) }}</button>
                    @endif
                @endforeach
            </div>
            <label for="receipt" class="flex flex-col items-center justify-center gap-2.5 py-9 px-5 rounded-2xl border-2 border-dashed border-line bg-bg cursor-pointer text-center" data-dropzone>
                <span class="w-[52px] h-[52px] rounded-2xl bg-brand-soft text-brand-ink flex items-center justify-center"><x-icon name="upload" size="24" /></span>
                <span class="font-semibold text-[15px]" data-file-name>Upload receipt screenshot</span>
                <span class="text-[13px] text-muted">JPG, PNG or WEBP, up to {{ (int) (config('unlockhub.receipts.max_kb') / 1024) }} MB</span>
                <input id="receipt" name="receipt" type="file" accept="image/*" class="sr-only" required>
            </label>
            <x-field-error name="receipt" />
            <button class="btn btn-primary btn-lg">Submit for approval</button>
        </form>
    </section>

    {{-- History --}}
    <aside class="flex-[1_1_340px] min-w-0">
        <div class="card p-5 flex flex-col gap-3">
            <div class="flex justify-between items-center">
                <h2 class="m-0 text-lg font-bold">Recent top-ups</h2>
                @if ($pendingCount) <span class="badge badge-pending">{{ $pendingCount }} pending</span> @endif
            </div>
            @forelse ($history as $h)
                <div class="p-3 rounded-[10px] bg-surface-2 flex flex-col gap-1.5">
                    <div class="flex justify-between items-center gap-3">
                        <div class="flex flex-col gap-0.5">
                            <span class="font-bold">{{ money($h->status === 'approved' ? $h->credited_amount : $h->amount) }}</span>
                            <span class="text-xs text-muted">{{ $h->method->name }} · {{ $h->created_at->format('d M H:i') }}</span>
                        </div>
                        <x-status-badge :status="$h->status" />
                    </div>
                    @if ($h->status === 'rejected' && $h->reject_reason)
                        <span class="text-xs text-danger">Reason: {{ $h->reject_reason }}</span>
                    @endif
                    @if ($h->status === 'approved' && (float) $h->credited_amount !== (float) $h->amount)
                        <span class="text-xs text-muted">You sent {{ money($h->amount) }}, credited {{ money($h->credited_amount) }}.</span>
                    @endif
                    <a href="{{ route('account.funds.receipt', $h) }}" target="_blank" rel="noopener" class="text-xs">View receipt</a>
                </div>
            @empty
                <p class="m-0 text-sm text-muted">No top-ups yet.</p>
            @endforelse
            {{ $history->links() }}
            <p class="m-0 text-[13px] text-muted leading-normal">Rejected? Check the reason, then upload the correct receipt again.</p>
        </div>
    </aside>
</div>
@endif
@endsection
