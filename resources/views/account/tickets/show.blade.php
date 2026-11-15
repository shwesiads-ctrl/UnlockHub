@extends('layouts.account', ['title' => __('Ticket').' #'.$ticket->ticket_no])

@section('content')
<div class="card p-5 flex flex-wrap gap-4 items-center">
    <div class="flex-1 min-w-60">
        <h2 class="m-0 text-lg font-bold">{{ $ticket->subject }}</h2>
        <div class="text-[13px] text-muted">
            #{{ $ticket->ticket_no }} · {{ __(\App\Models\Ticket::CATEGORIES[$ticket->category]) }}
            @if ($ticket->order) · {{ __('Order') }} <a href="{{ route('account.orders.show', $ticket->order) }}">#{{ $ticket->order->order_no }}</a> @endif
            · {{ $ticket->created_at->format('d M Y H:i') }}
        </div>
    </div>
    <span class="badge {{ $ticket->badgeClass() }} text-sm">{{ __($ticket->statusLabel()) }}</span>
</div>

@include('tickets._thread', ['messages' => $ticket->publicMessages, 'staffView' => false, 'fileRoute' => 'account.tickets.attachment'])

@if ($ticket->customerCanReply())
    <form method="POST" action="{{ route('account.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="card p-5 flex flex-col gap-3" data-submit-once>
        @csrf
        @if ($ticket->status === 'resolved')
            <p class="m-0 text-[13px] text-muted">{{ __('This ticket is marked resolved. Replying reopens it') }} ({{ __('until') }} {{ $ticket->resolved_at->addDays(\App\Models\Ticket::reopenDays())->format('d M') }}).</p>
        @endif
        <label for="message" class="label">{{ __('Your reply') }}</label>
        <textarea id="message" name="message" rows="4" maxlength="5000" class="input" required>{{ old('message') }}</textarea>
        <x-field-error name="message" />
        <input name="files[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt" class="input py-2" aria-label="{{ __('Attach files') }}">
        <x-field-error name="files" />
        <div class="flex gap-2">
            <button class="btn btn-primary">{{ __('Send reply') }}</button>
        </div>
    </form>
    @if ($ticket->status === 'resolved')
        <form method="POST" action="{{ route('account.tickets.reopen', $ticket) }}">@csrf<button class="btn">{{ __('Reopen without replying') }}</button></form>
    @endif
@else
    <p class="m-0 text-sm text-muted">{{ __('This ticket is closed.') }} <a href="{{ route('account.tickets.create', $ticket->order ? ['order' => $ticket->order->order_no] : []) }}">{{ __('Open a new ticket') }}</a></p>
@endif
@endsection
