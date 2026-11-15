@extends('layouts.app', ['title' => 'Contact'])

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-10 flex flex-col gap-6">
    <div class="flex flex-col gap-1.5">
        <h1 class="m-0 text-[34px] font-bold">Contact us</h1>
        <p class="m-0 text-muted text-[15px]">Support hours: {{ setting('site.support_hours', '9 AM – 10 PM') }}. For order problems, include your order number.</p>
    </div>

    <div class="flex flex-wrap gap-6 items-start">
        <form method="POST" action="{{ route('contact.send') }}" class="flex-[999_1_520px] min-w-0 card p-6 flex flex-col gap-4" novalidate>
            @csrf
            <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                <div>
                    <label for="name" class="label">Name</label>
                    <input id="name" name="name" value="{{ old('name', auth()->user()?->name) }}" class="input" required>
                    <x-field-error name="name" />
                </div>
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="input" required>
                    <x-field-error name="email" />
                </div>
            </div>
            <div>
                <label for="subject" class="label">Subject</label>
                <input id="subject" name="subject" value="{{ old('subject') }}" class="input" required>
                <x-field-error name="subject" />
            </div>
            <div>
                <label for="message" class="label">Message</label>
                <textarea id="message" name="message" rows="6" class="input" required>{{ old('message') }}</textarea>
                <x-field-error name="message" />
            </div>
            <div class="hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <button class="btn btn-primary btn-lg self-start">Send message</button>
        </form>

        <aside class="flex-[1_1_320px] min-w-0 card p-5 flex flex-col gap-3 text-sm">
            <h2 class="m-0 text-lg font-bold">Quick channels</h2>
            @php
                $channels = array_filter([
                    'Telegram' => setting('social.telegram'),
                    'Viber' => setting('social.viber'),
                    'Facebook' => setting('social.facebook'),
                    'YouTube' => setting('social.youtube'),
                ]);
            @endphp
            @forelse ($channels as $label => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn justify-start">{{ $label }}</a>
            @empty
                <span class="text-muted">Use the form — we'll reply by email.</span>
            @endforelse
            @if ($phone = setting('contact.phone'))
                <div class="mt-2"><span class="text-muted">Phone:</span> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="font-semibold">{{ $phone }}</a></div>
            @endif
            @if ($address = setting('contact.address'))
                <div><span class="text-muted">Address:</span> {{ $address }}</div>
            @endif
            <a href="{{ route('kb.index') }}" class="mt-2">Browse the Knowledge Base →</a>
        </aside>
    </div>
</div>
@endsection
