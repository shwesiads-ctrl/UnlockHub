@extends('layouts.account', ['title' => __('Bulk order from file')])

@section('content')
<div class="card p-5 flex flex-col gap-1">
    <span class="text-[13px] text-muted">{{ __('Service') }}</span>
    <a href="{{ route('services.show', $service) }}" class="font-bold text-lg text-ink">{{ $service->name }}</a>
    <span class="text-sm">{{ __('Price') }}: <strong>{{ price($service->priceFor(auth()->user())) }}</strong> / {{ __('item') }}</span>
</div>

<section class="card p-5 flex flex-col gap-3">
    <h2 class="m-0 text-lg font-bold">1. {{ __('Download a template') }}</h2>
    <p class="m-0 text-sm text-muted">{{ __('One row per order. Columns') }}: <strong>{{ $service->fields->pluck('label')->join(', ') ?: 'IMEI' }}</strong>. {{ __('The Excel template keeps every column as Text, so IMEIs keep their leading zeros.') }}</p>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('account.bulk.template', [$service, 'xlsx']) }}" class="btn"><x-icon name="file" size="16" />{{ __('Excel (.xlsx)') }}</a>
        <a href="{{ route('account.bulk.template', [$service, 'csv']) }}" class="btn"><x-icon name="file" size="16" />CSV</a>
    </div>
</section>

<form method="POST" action="{{ route('account.bulk.upload', $service) }}" enctype="multipart/form-data" class="card p-5 flex flex-col gap-3" data-submit-once>
    @csrf
    <h2 class="m-0 text-lg font-bold">2. {{ __('Upload your file') }}</h2>
    <label for="file" class="sr-only">{{ __('File') }}</label>
    <input id="file" name="file" type="file" accept=".csv,.xlsx,text/csv" class="input py-2" required>
    <x-field-error name="file" />
    <p class="m-0 text-xs text-muted">.csv / .xlsx · {{ __('max') }} {{ number_format(\App\Services\BulkUploadService::maxKb() / 1024, 1) }} MB · {{ __('max') }} {{ number_format(\App\Services\BulkUploadService::maxRows()) }} {{ __('rows') }}.
        {{ __('Next you can match columns and see every row checked. Nothing is ordered until you confirm.') }}</p>
    <button class="btn btn-primary self-start">{{ __('Upload & check') }}</button>
</form>
@endsection
