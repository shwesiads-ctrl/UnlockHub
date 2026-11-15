@extends('layouts.account', ['title' => __('Check your bulk order')])

@section('content')
@php
    $enough = $preview['balance'] >= $preview['total'];
    $blocked = ($service->duplicate_policy ?? 'warn') === 'block';
@endphp

<div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(150px,1fr))]">
    <div class="card p-4 flex flex-col"><span class="text-xs text-muted">{{ __('Rows in file') }}</span><span class="font-display text-2xl font-bold">{{ count($preview['rows']) }}</span></div>
    <div class="card p-4 flex flex-col"><span class="text-xs text-muted">{{ __('Valid') }}</span><span class="font-display text-2xl font-bold text-ok">{{ $preview['valid'] }}</span></div>
    <div class="card p-4 flex flex-col"><span class="text-xs text-muted">{{ __('Invalid') }}</span><span class="font-display text-2xl font-bold text-danger">{{ $preview['invalid'] }}</span></div>
    <div class="card p-4 flex flex-col"><span class="text-xs text-muted">{{ __('Duplicate') }}</span><span class="font-display text-2xl font-bold text-warn">{{ $preview['duplicate'] }}</span></div>
    <div class="card p-4 flex flex-col"><span class="text-xs text-muted">{{ __('Total') }} ({{ $preview['valid'] }} × {{ unit_price($preview['price'], $preview['price_currency']) }})</span><span class="font-display text-2xl font-bold">{{ unit_price($preview['total'], $preview['price_currency']) }}</span></div>
    <div class="card p-4 flex flex-col"><span class="text-xs text-muted">{{ $preview['price_currency'] === 'points' ? __('Points balance') : __('Wallet') }}</span><span @class(['font-display text-2xl font-bold', 'text-danger' => ! $enough])>{{ unit_price($preview['balance'], $preview['price_currency']) }}</span>
        @unless ($enough) <a href="{{ route('account.funds.index') }}" class="text-xs">{{ __('Add funds') }}</a> @endunless</div>
</div>

{{-- Column mapping --}}
<form method="POST" action="{{ route('account.bulk.mapping', [$service, $token]) }}" class="card p-5 flex flex-col gap-3">
    @csrf
    <div class="flex flex-wrap justify-between items-center gap-2">
        <h2 class="m-0 text-lg font-bold">{{ __('Match columns') }} <span class="text-sm font-normal text-muted">· {{ $data['file'] }}</span></h2>
        <a href="{{ route('account.bulk.create', $service) }}" class="text-sm">{{ __('Upload a different file') }}</a>
    </div>
    <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
        @foreach ($service->fields as $f)
            <div>
                <label for="map_{{ $f->name }}" class="label text-xs">{{ $f->label }} @if ($f->is_required)<span class="text-danger">*</span>@endif</label>
                <select id="map_{{ $f->name }}" name="map[{{ $f->name }}]" class="input h-10">
                    <option value="">— {{ __('not in file') }} —</option>
                    @foreach ($data['header'] as $i => $h)
                        <option value="{{ $i }}" @selected(($data['mapping'][$f->name] ?? null) === $i)>{{ \App\Support\Spreadsheet::colName($i) }}: {{ $h !== '' ? $h : '(no header)' }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>
    <button class="btn self-start">{{ __('Apply & check again') }}</button>
</form>

{{-- Rows --}}
<section class="card overflow-hidden">
    <div class="px-5 py-4 flex flex-wrap gap-2 items-center">
        <h2 class="m-0 text-lg font-bold mr-auto">{{ __('Rows') }}</h2>
        @foreach (['all' => __('All'), 'valid' => __('Valid'), 'invalid' => __('Invalid'), 'duplicate' => __('Duplicate')] as $k => $l)
            <button type="button" class="btn h-8 text-xs" data-row-filter="{{ $k }}">{{ $l }}</button>
        @endforeach
        @if ($preview['invalid'] + $preview['duplicate'] > 0)
            <a href="{{ route('account.bulk.errors', [$service, $token]) }}" class="btn h-8 text-xs">{{ __('Download problem rows') }}</a>
        @endif
    </div>
    <ol class="sm:hidden m-0 p-0 list-none flex flex-col divide-y divide-line max-h-[65vh] overflow-y-auto" data-rows>
        @foreach ($preview['rows'] as $r)
            <li class="px-4 py-3 flex flex-col gap-1" data-status="{{ $r['status'] }}">
                <div class="flex justify-between items-center gap-2">
                    <span class="text-xs text-muted">{{ __('Line') }} {{ $r['line'] }}</span>
                    <span @class(['badge', 'badge-success' => $r['status'] === 'valid', 'badge-rejected' => $r['status'] === 'invalid', 'badge-pending' => $r['status'] === 'duplicate'])>{{ __(ucfirst($r['status'])) }}</span>
                </div>
                @foreach ($service->fields as $f)
                    @if ($r['input'][$f->name] !== '') <div class="text-[13px]"><span class="text-muted">{{ $f->label }}:</span> <span class="font-mono break-all">{{ $r['input'][$f->name] }}</span></div> @endif
                @endforeach
                @if ($r['reason']) <div class="text-xs text-danger">{{ $r['reason'] }}</div> @endif
            </li>
        @endforeach
    </ol>
    <div class="hidden sm:block table-wrap max-h-[60vh] overflow-y-auto">
        <table class="table min-w-[560px]" data-rows>
            <thead class="sticky top-0 bg-surface"><tr><th>{{ __('Line') }}</th>@foreach ($service->fields as $f)<th>{{ $f->label }}</th>@endforeach<th>{{ __('Check') }}</th></tr></thead>
            <tbody>
            @foreach ($preview['rows'] as $r)
                <tr data-status="{{ $r['status'] }}">
                    <td class="text-muted">{{ $r['line'] }}</td>
                    @foreach ($service->fields as $f)<td class="font-mono text-[13px] break-all">{{ $r['input'][$f->name] }}</td>@endforeach
                    <td>
                        <span @class(['badge', 'badge-success' => $r['status'] === 'valid', 'badge-rejected' => $r['status'] === 'invalid', 'badge-pending' => $r['status'] === 'duplicate'])>{{ __(ucfirst($r['status'])) }}</span>
                        @if ($r['reason']) <div class="text-xs text-muted mt-1">{{ $r['reason'] }}</div> @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

<form method="POST" action="{{ route('account.bulk.confirm', [$service, $token]) }}" class="card p-5 flex flex-col gap-3" data-submit-once>
    @csrf
    <h2 class="m-0 text-lg font-bold">{{ __('Place the orders') }}</h2>
    <p class="m-0 text-sm">{{ __('Only valid rows are ordered') }} ({{ $preview['valid'] }}). {{ __('Price and wallet balance are checked again when you confirm.') }}</p>
    @if ($preview['open_duplicates'] > 0 && ! $blocked)
        <label class="flex items-start gap-2.5 text-sm">
            <input type="checkbox" name="include_duplicates" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]">
            <span>{{ __('Also order the') }} {{ $preview['open_duplicates'] }} {{ __('row(s) that are already in an open order (charged again).') }}</span>
        </label>
    @endif
    <label class="flex items-start gap-2.5 text-sm text-muted">
        <input type="checkbox" name="confirm" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" required>
        <span>{{ __('I checked the details. Wrong entries are non-refundable.') }}</span>
    </label>
    <x-field-error name="confirm" />
    <button class="btn btn-primary btn-lg self-start" @disabled($preview['valid'] === 0 && $preview['open_duplicates'] === 0)>
        {{ __('Place') }} {{ $preview['valid'] }} {{ __('order(s)') }} · {{ price($preview['total']) }}
    </button>
</form>
@endsection
