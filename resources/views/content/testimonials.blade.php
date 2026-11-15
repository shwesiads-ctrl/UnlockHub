@extends('layouts.app', ['title' => 'Testimonials'])

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-10 flex flex-col gap-6">
    <div class="flex flex-col gap-1.5">
        <h1 class="m-0 text-[34px] font-bold">{{ __('What resellers say') }}</h1>
        <p class="m-0 text-muted text-[15px]">{{ __('Reviews from shops and resellers using :site.', ['site' => setting('site.name', config('app.name'))]) }}</p>
    </div>

    <div class="flex flex-wrap gap-6 items-start">
        <div class="flex-[999_1_600px] min-w-0">
            @if ($testimonials->isEmpty())
                <div class="card p-10 text-center text-muted">{{ __('No reviews yet — be the first.') }}</div>
            @else
                <div class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(280px,1fr))]">
                    @foreach ($testimonials as $t)
                        <figure class="card m-0 p-5 flex flex-col gap-3">
                            <div class="flex gap-0.5 text-point" role="img" aria-label="{{ __(':n out of 5 stars', ['n' => $t->rating]) }}">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-icon name="star" size="16" @class(['fill-current' => $i <= $t->rating, 'opacity-30' => $i > $t->rating]) />
                                @endfor
                            </div>
                            <blockquote class="m-0 text-[15px] leading-relaxed">“{{ $t->message }}”</blockquote>
                            <figcaption class="text-sm font-semibold text-muted">— {{ $t->name }} · {{ $t->created_at->format('M Y') }}</figcaption>
                        </figure>
                    @endforeach
                </div>
                <div class="mt-5">{{ $testimonials->links() }}</div>
            @endif
        </div>

        <aside class="flex-[1_1_320px] min-w-0">
            @auth
                <form method="POST" action="{{ route('testimonials.store') }}" class="card p-5 flex flex-col gap-3.5">
                    @csrf
                    <h2 class="m-0 text-lg font-bold">{{ __('Write a review') }}</h2>
                    @if ($mine && ! $mine->is_approved)
                        <p class="m-0 text-[13px] text-warn">{{ __('Your last review is waiting for approval. Sending again replaces it.') }}</p>
                    @endif
                    <fieldset class="m-0 p-0 border-0">
                        <legend class="label">{{ __('Rating') }}</legend>
                        <div class="flex gap-3">
                            @for ($i = 5; $i >= 1; $i--)
                                <label class="flex items-center gap-1.5 text-sm"><input type="radio" name="rating" value="{{ $i }}" class="accent-[var(--uh-brand)]" @checked((int) old('rating', 5) === $i)>{{ $i }}</label>
                            @endfor
                        </div>
                    </fieldset>
                    <div>
                        <label for="message" class="label">{{ __('Your review') }}</label>
                        <textarea id="message" name="message" rows="4" maxlength="600" class="input" required>{{ old('message') }}</textarea>
                        <x-field-error name="message" />
                    </div>
                    <button class="btn btn-primary">{{ __('Submit review') }}</button>
                </form>
            @else
                <div class="card p-5 flex flex-col gap-3 text-sm">
                    <span class="font-semibold">{{ __('Are you a customer?') }}</span>
                    <span class="text-muted">{{ __('Log in to leave a review.') }}</span>
                    <a href="{{ route('login') }}" class="btn btn-primary">{{ __('Log in') }}</a>
                </div>
            @endauth
        </aside>
    </div>
</div>
@endsection
