@extends('layouts.account', ['title' => __('New ticket')])

@section('content')
<form method="POST" action="{{ route('account.tickets.store') }}" enctype="multipart/form-data" class="card p-6 flex flex-col gap-4 max-w-3xl" data-submit-once>
    @csrf
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
        <div>
            <label for="category" class="label">{{ __('Topic') }}</label>
            <select id="category" name="category" class="input" required>
                @foreach (\App\Models\Ticket::CATEGORIES as $k => $l)
                    <option value="{{ $k }}" @selected(old('category', $order ? 'order' : 'other') === $k)>{{ __($l) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="order_id" class="label">{{ __('Order') }} <span class="font-normal text-muted">({{ __('optional') }})</span></label>
            <select id="order_id" name="order_id" class="input">
                <option value="">—</option>
                @foreach ($orders as $o)
                    <option value="{{ $o->id }}" @selected((int) old('order_id', $order?->id) === $o->id)>#{{ $o->order_no }} · {{ \Illuminate\Support\Str::limit($o->service_name, 40) }} · {{ $o->imei }}</option>
                @endforeach
            </select>
            <x-field-error name="order_id" />
        </div>
    </div>
    <div>
        <label for="subject" class="label">{{ __('Subject') }}</label>
        <input id="subject" name="subject" maxlength="150" value="{{ old('subject', $order ? 'Order #'.$order->order_no : '') }}" class="input" required>
        <x-field-error name="subject" />
    </div>
    <div>
        <label for="message" class="label">{{ __('Message') }}</label>
        <textarea id="message" name="message" rows="7" maxlength="5000" class="input" required>{{ old('message') }}</textarea>
        <x-field-error name="message" />
    </div>
    <div>
        <label for="files" class="label">{{ __('Screenshots / files') }} <span class="font-normal text-muted">(max {{ \App\Services\TicketService::MAX_FILES }} · JPG, PNG, WebP, PDF, TXT · 5 MB each)</span></label>
        <input id="files" name="files[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt" class="input py-2">
        <x-field-error name="files" /><x-field-error name="files.0" /><x-field-error name="files.1" /><x-field-error name="files.2" />
    </div>
    <button class="btn btn-primary self-start">{{ __('Send ticket') }}</button>
</form>
@endsection
