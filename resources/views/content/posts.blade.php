@php $isKb = $type === 'kb'; @endphp
@extends('layouts.app', ['title' => $isKb ? __('Knowledge Base') : __('Blog')])

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-10 flex flex-col gap-6">
    <div class="flex flex-wrap gap-4 items-end justify-between">
        <div class="flex flex-col gap-1.5">
            <h1 class="m-0 text-[34px] font-bold">{{ $isKb ? 'Knowledge Base' : 'Blog' }}</h1>
            <p class="m-0 text-muted text-[15px]">{{ $isKb ? 'How-to guides, ordering tips and answers to common questions.' : 'News, new services and updates.' }}</p>
        </div>
        <form method="GET" role="search" class="flex gap-2">
            <label for="q" class="sr-only">{{ __('Search') }}</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="{{ $isKb ? __('Search articles and questions…') : __('Search posts…') }}" class="input h-11 w-full sm:w-72">
            <button class="btn h-11">{{ __('Search') }}</button>
        </form>
    </div>

    @if (! $isKb && $posts->isEmpty())
        <div class="card p-10 text-center text-muted">{{ $search ? 'Nothing matches "'.$search.'".' : 'Nothing published yet.' }}</div>
    @elseif ($isKb)
        @if ($faqs->isNotEmpty())
            <section class="flex flex-col gap-3" aria-labelledby="faq-heading">
                <div>
                    <h2 id="faq-heading" class="m-0 text-2xl font-bold">Questions &amp; Answers</h2>
                    <p class="m-0 mt-1 text-sm text-muted">Quick answers to common questions.</p>
                </div>
                <div class="card divide-y divide-[var(--uh-line)] overflow-hidden">
                    @foreach ($faqs as $faq)
                        <details class="group px-5 py-4">
                            <summary class="cursor-pointer list-none flex items-center justify-between gap-4 font-semibold text-ink [&::-webkit-details-marker]:hidden">
                                <span>{{ $faq->question }}</span>
                                <span aria-hidden="true" class="text-brand-ink text-xl leading-none transition-transform group-open:rotate-45">+</span>
                            </summary>
                            <div class="mt-3 text-sm leading-relaxed text-muted prose prose-invert max-w-none">{!! Illuminate\Support\Str::markdown($faq->answer, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                        </details>
                    @endforeach
                </div>
                {{ $faqs->links() }}
            </section>
        @endif

        @if ($posts->isNotEmpty())
            <section class="flex flex-col gap-3" aria-labelledby="articles-heading">
                <h2 id="articles-heading" class="m-0 text-2xl font-bold">Articles &amp; Guides</h2>
                <div class="card divide-y divide-[var(--uh-line)]">
                    @foreach ($posts as $post)
                        <a href="{{ route('kb.show', $post->slug) }}" class="flex items-center gap-4 px-5 py-4 no-underline text-ink hover:text-ink hover:bg-surface-2">
                            <span class="w-10 h-10 rounded-xl bg-brand-soft text-brand-ink flex items-center justify-center shrink-0"><x-icon name="file" /></span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-semibold">{{ $post->title }}</span>
                                @if ($post->excerpt) <span class="block text-sm text-muted truncate">{{ $post->excerpt }}</span> @endif
                            </span>
                            <x-icon name="list" class="text-muted" />
                        </a>
                    @endforeach
                </div>
                {{ $posts->links() }}
            </section>
        @endif
        @if ($posts->isEmpty() && $faqs->isEmpty())
            <div class="card p-10 text-center text-muted">{{ $search ? 'Nothing matches "'.$search.'".' : 'Nothing published yet.' }}</div>
        @endif
    @else
        <div class="grid gap-5 grid-cols-[repeat(auto-fill,minmax(300px,1fr))]">
            @foreach ($posts as $post)
                <a href="{{ route('blog.show', $post->slug) }}" class="card overflow-hidden no-underline text-ink hover:text-ink flex flex-col">
                    @if ($post->cover_path)
                        <img src="{{ Storage::disk('public')->url($post->cover_path) }}" alt="" class="w-full h-44 object-cover">
                    @else
                        <div class="w-full h-44 bg-brand-soft flex items-center justify-center text-brand-ink" aria-hidden="true"><x-icon name="file" size="36" /></div>
                    @endif
                    <div class="p-5 flex flex-col gap-2">
                        <span class="text-xs text-muted">{{ $post->published_at?->format('d M Y') }}</span>
                        <span class="font-display text-lg font-bold leading-snug">{{ $post->title }}</span>
                        @if ($post->excerpt) <span class="text-sm text-muted leading-normal">{{ $post->excerpt }}</span> @endif
                    </div>
                </a>
            @endforeach
        </div>
        {{ $posts->links() }}
    @endif
</div>
@endsection
