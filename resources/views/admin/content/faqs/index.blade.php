@extends('layouts.admin', ['title' => 'Knowledge Base Q&A', 'subtitle' => 'Manage frequently asked questions and answers'])

@section('content')
@include('admin.content._tabs')

<div class="flex flex-wrap gap-3 items-center">
    <form method="GET" class="flex gap-2">
        <label for="q" class="sr-only">Search questions</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Search questions…" class="input h-10 w-64">
        <button class="btn h-10">Search</button>
    </form>
    <a href="{{ route('admin.kb-faqs.create') }}" class="btn btn-primary h-10 ml-auto">+ Add question</a>
</div>

<section class="card table-wrap">
    <table class="table min-w-[680px]">
        <thead><tr><th>Question</th><th>Display order</th><th>Status</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        @forelse ($faqs as $faq)
            <tr>
                <td class="font-semibold">{{ $faq->question }}</td>
                <td class="text-muted">{{ $faq->sort }}</td>
                <td><span class="badge {{ $faq->is_published ? 'badge-success' : 'badge-pending' }}">{{ $faq->is_published ? 'Published' : 'Draft' }}</span></td>
                <td class="text-muted whitespace-nowrap">{{ $faq->updated_at->diffForHumans() }}</td>
                <td class="text-right"><a href="{{ route('admin.kb-faqs.edit', $faq) }}" class="btn h-9 text-xs">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-10">No questions yet. Add the first answer for your customers.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $faqs->links() }}
@endsection
