@php
    $tabs = [
        ['Knowledge Base', route('admin.posts.index', ['type' => 'kb']), request()->routeIs('admin.posts.*') && (request('type', $post->type ?? 'kb') === 'kb')],
        ['Blog', route('admin.posts.index', ['type' => 'blog']), request()->routeIs('admin.posts.*') && (request('type', $post->type ?? null) === 'blog')],
        ['Testimonials', route('admin.testimonials.index'), request()->routeIs('admin.testimonials.*')],
        ['Downloads', route('admin.downloads.index'), request()->routeIs('admin.downloads.*')],
        ['Announcements', route('admin.announcements.index'), request()->routeIs('admin.announcements.*')],
    ];
@endphp
<nav aria-label="Content sections" class="flex flex-wrap gap-2">
    @foreach ($tabs as [$label, $url, $active])
        <a href="{{ $url }}" @class(['btn h-10', 'btn-primary' => $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn h-10 ml-auto">View site</a>
</nav>
