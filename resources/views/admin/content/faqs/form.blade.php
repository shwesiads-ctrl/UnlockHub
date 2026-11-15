@extends('layouts.admin', ['title' => $faq->exists ? 'Edit Q&A' : 'Add Q&A', 'subtitle' => 'Knowledge Base / Questions & Answers'])

@section('content')
@include('admin.content._tabs')

<form method="POST" action="{{ $faq->exists ? route('admin.kb-faqs.update', $faq) : route('admin.kb-faqs.store') }}" class="card p-6 flex flex-col gap-5 max-w-4xl">
    @csrf
    @if ($faq->exists) @method('PUT') @endif

    <div>
        <label for="question" class="label">Question</label>
        <input id="question" name="question" value="{{ old('question', $faq->question) }}" maxlength="255" class="input" required autofocus>
        <x-field-error name="question" />
    </div>
    <div>
        <div class="flex justify-between items-baseline gap-3">
            <label for="answer" class="label">Answer</label>
            <span class="text-xs text-muted">Markdown supported</span>
        </div>
        <textarea id="answer" name="answer" rows="12" maxlength="20000" class="input font-mono text-sm leading-relaxed" required>{{ old('answer', $faq->answer) }}</textarea>
        <x-field-error name="answer" />
    </div>
    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label for="sort" class="label">Display order</label>
            <input id="sort" name="sort" type="number" min="0" value="{{ old('sort', $faq->sort ?? 0) }}" class="input">
            <p class="mt-1 text-xs text-muted">Lower numbers appear first.</p>
        </div>
        <label class="flex items-center gap-2.5 text-sm font-semibold sm:pt-8">
            <input type="checkbox" name="is_published" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('is_published', $faq->exists ? $faq->is_published : true))>
            Published on the Knowledge Base
        </label>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.kb-faqs.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary">{{ $faq->exists ? 'Save changes' : 'Add question' }}</button>
    </div>
</form>

@if ($faq->exists)
    <form method="POST" action="{{ route('admin.kb-faqs.destroy', $faq) }}" class="mt-4" onsubmit="return confirm('Delete this question and answer?')">
        @csrf @method('DELETE')
        <button class="btn btn-danger h-10">Delete</button>
    </form>
@endif
@endsection
