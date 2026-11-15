{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ setting('site.name', config('app.name')) }}</title>
    <link>{{ url('/') }}</link>
    <description>New services and news</description>
    <language>en</language>
    <atom:link href="{{ route('rss') }}" rel="self" type="application/rss+xml" />
    @foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ route('blog.show', $post->slug) }}</link>
        <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
        <pubDate>{{ $post->published_at?->toRssString() }}</pubDate>
        <description>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 200) }}</description>
    </item>
    @endforeach
    @foreach ($services as $service)
    <item>
        <title>New service: {{ $service->name }}</title>
        <link>{{ route('services.show', $service) }}</link>
        <guid isPermaLink="true">{{ route('services.show', $service) }}</guid>
        <pubDate>{{ $service->created_at->toRssString() }}</pubDate>
        <description>{{ \Illuminate\Support\Str::limit((string) $service->description, 200) }}</description>
    </item>
    @endforeach
</channel>
</rss>
