@extends('layouts.admin', ['title' => 'Content', 'subtitle' => $type === 'kb' ? 'Knowledge Base articles' : 'Blog posts'])

@section('content')
@include('admin.content._tabs')

<div class="flex flex-wrap gap-2 items-center">
    <form method="GET" class="flex gap-2">
        <input type="hidden" name="type" value="{{ $type }}">
        <label for="q" class="sr-only">Search</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Search title…" class="input h-10 w-64">
        <button class="btn h-10">Search</button>
    </form>
    <a href="{{ route('admin.posts.create', ['type' => $type]) }}" class="btn btn-primary h-10 ml-auto">+ New {{ $type === 'kb' ? 'article' : 'post' }}</a>
</div>

<section class="card table-wrap">
    <table class="table min-w-[680px]">
        <thead><tr><th>Title</th><th>Status</th><th>Published</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        @forelse ($posts as $p)
            <tr>
                <td><a href="{{ route('admin.posts.edit', $p) }}" class="font-semibold text-ink hover:text-brand-ink no-underline">{{ $p->title }}</a><div class="text-xs text-muted font-mono">/{{ $p->type }}/{{ $p->slug }}</div></td>
                <td>
                    @if ($p->is_published && $p->published_at && $p->published_at->isFuture())
                        <span class="badge badge-info">Scheduled</span>
                    @elseif ($p->is_published)
                        <span class="badge badge-success">Published</span>
                    @else
                        <span class="badge badge-pending">Draft</span>
                    @endif
                </td>
                <td class="text-muted whitespace-nowrap">{{ $p->published_at?->format('d M Y') ?? '—' }}</td>
                <td class="text-muted whitespace-nowrap">{{ $p->updated_at->diffForHumans() }}</td>
                <td class="text-right whitespace-nowrap">
                    @if ($p->is_published)
                        <a href="{{ $p->type === 'kb' ? route('kb.show', $p->slug) : route('blog.show', $p->slug) }}" target="_blank" rel="noopener" class="btn h-9 text-xs">View</a>
                    @endif
                    <a href="{{ route('admin.posts.edit', $p) }}" class="btn h-9 text-xs">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-10">Nothing yet. <a href="{{ route('admin.posts.create', ['type' => $type]) }}">Write the first one</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $posts->links() }}
@endsection
