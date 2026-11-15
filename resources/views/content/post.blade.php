@php $isKb = $post->type === 'kb'; $index = $isKb ? route('kb.index') : route('blog.index'); @endphp
@extends('layouts.app', ['title' => $post->title])

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-10 flex flex-wrap gap-8 items-start">
    <article class="flex-[999_1_600px] min-w-0 flex flex-col gap-5">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-muted flex gap-2"><a href="{{ $index }}">{{ $isKb ? 'Knowledge Base' : 'Blog' }}</a><span>/</span><span class="text-ink truncate">{{ $post->title }}</span></nav>
        <h1 class="m-0 text-[34px] leading-tight font-bold">{{ $post->title }}</h1>
        <p class="m-0 text-sm text-muted">{{ $post->published_at?->format('d M Y') }}@if ($post->author) · {{ $post->author->name }}@endif</p>
        @if ($post->cover_path)
            <img src="{{ Storage::disk('public')->url($post->cover_path) }}" alt="" class="w-full max-h-[420px] object-cover rounded-2xl">
        @endif
        <div class="prose-uh card p-6 sm:p-8">
            {!! \Illuminate\Support\Str::markdown($post->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
        </div>
    </article>

    <aside class="flex-[1_1_280px] min-w-0 flex flex-col gap-4">
        @if ($related->isNotEmpty())
            <section class="card p-5 flex flex-col gap-3">
                <h2 class="m-0 text-lg font-bold">More {{ $isKb ? 'articles' : 'posts' }}</h2>
                @foreach ($related as $r)
                    <a href="{{ $isKb ? route('kb.show', $r->slug) : route('blog.show', $r->slug) }}" class="text-sm font-medium">{{ $r->title }}</a>
                @endforeach
            </section>
        @endif
        <section class="card p-5 flex flex-col gap-2 text-sm">
            <h2 class="m-0 text-lg font-bold">{{ __('Still need help?') }}</h2>
            <span class="text-muted">Our team replies {{ setting('site.support_hours', '9 AM – 10 PM') }}.</span>
            <a href="{{ route('contact') }}" class="btn btn-primary mt-1">{{ __('Contact support') }}</a>
        </section>
    </aside>
</div>
@endsection
