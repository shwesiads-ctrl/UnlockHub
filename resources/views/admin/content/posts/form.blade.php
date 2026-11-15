@extends('layouts.admin', [
    'title' => $post->exists ? $post->title : ($post->type === 'kb' ? 'New article' : 'New blog post'),
    'subtitle' => 'Content / '.($post->type === 'kb' ? 'Knowledge Base' : 'Blog'),
])

@section('content')
@include('admin.content._tabs')

<form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" class="flex flex-wrap gap-5 items-start" novalidate>
    @csrf
    @if ($post->exists) @method('PUT') @endif

    <div class="flex-[999_1_600px] min-w-0 card p-6 flex flex-col gap-4">
        <div>
            <label for="title" class="label">Title</label>
            <input id="title" name="title" value="{{ old('title', $post->title) }}" class="input text-lg font-semibold" required>
            <x-field-error name="title" />
        </div>
        <div>
            <label for="excerpt" class="label">Short summary <span class="font-normal text-muted">(lists and RSS)</span></label>
            <input id="excerpt" name="excerpt" value="{{ old('excerpt', $post->excerpt) }}" maxlength="255" class="input">
        </div>
        <div>
            <div class="flex justify-between items-baseline">
                <label for="body" class="label">Body</label>
                <span class="text-xs text-muted">Markdown: <code>## Heading</code>, <code>**bold**</code>, <code>- list</code>, <code>[link](https://…)</code></span>
            </div>
            <textarea id="body" name="body" rows="22" class="input font-mono text-sm leading-relaxed" required>{{ old('body', $post->body) }}</textarea>
            <x-field-error name="body" />
        </div>
    </div>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <section class="card p-5 flex flex-col gap-4">
            <div>
                <label for="type" class="label">Section</label>
                <select id="type" name="type" class="input h-11">
                    <option value="kb" @selected(old('type', $post->type) === 'kb')>Knowledge Base</option>
                    <option value="blog" @selected(old('type', $post->type) === 'blog')>Blog</option>
                </select>
            </div>
            <div>
                <label for="slug" class="label">URL slug <span class="font-normal text-muted">(empty = from title)</span></label>
                <input id="slug" name="slug" value="{{ old('slug', $post->slug) }}" class="input h-11 font-mono text-sm">
                <x-field-error name="slug" />
            </div>
            <label class="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="is_published" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('is_published', $post->is_published))>Published</label>
            <div>
                <label for="published_at" class="label">Publish date <span class="font-normal text-muted">(future = scheduled)</span></label>
                <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="input h-11">
            </div>
        </section>

        <section class="card p-5 flex flex-col gap-3">
            <span class="label mb-0">Cover image <span class="font-normal text-muted">(blog)</span></span>
            @if ($post->cover_path)
                <img src="{{ Storage::disk('public')->url($post->cover_path) }}" alt="Current cover" class="w-full h-36 object-cover rounded-xl">
                <label class="flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_cover" value="1">Remove cover</label>
            @endif
            <label for="cover" class="sr-only">Upload cover</label>
            <input id="cover" name="cover" type="file" accept="image/*" class="text-sm">
            <x-field-error name="cover" />
        </section>

        <div class="flex gap-2">
            <a href="{{ route('admin.posts.index', ['type' => $post->type]) }}" class="btn flex-1">Cancel</a>
            <button class="btn btn-primary flex-[2]">Save</button>
        </div>
    </aside>
</form>

@if ($post->exists)
    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="self-start" onsubmit="return confirm('Delete this {{ $post->type === 'kb' ? 'article' : 'post' }}?')">
        @csrf @method('DELETE')
        <button class="btn btn-danger h-10">Delete</button>
    </form>
@endif
@endsection
