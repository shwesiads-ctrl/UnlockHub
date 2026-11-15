@if ($paginator->hasPages())
@php
    $window = \Illuminate\Pagination\UrlWindow::make($paginator->onEachSide(1));
    $elements = array_filter([
        $window['first'],
        is_array($window['slider']) ? '...' : null,
        $window['slider'],
        is_array($window['last']) ? '...' : null,
        $window['last'],
    ]);
@endphp
<nav aria-label="{{ __('Pagination') }}" class="flex flex-wrap items-center gap-1.5">
    @if ($paginator->onFirstPage())
        <span class="btn h-9 px-3 opacity-50" aria-disabled="true" aria-label="{{ __('Previous page') }}">‹</span>
    @else
        <a class="btn h-9 px-3" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous page') }}">‹</a>
    @endif
    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="px-2 text-muted" aria-hidden="true">…</span>
        @else
            @foreach ($element as $page => $url)
                @if ($page === $paginator->currentPage())
                    <span class="btn btn-primary h-9 min-w-9 px-3" aria-current="page" aria-label="{{ __('Page :n', ['n' => $page]) }}">{{ $page }}</span>
                @else
                    <a class="btn h-9 min-w-9 px-3" href="{{ $url }}" aria-label="{{ __('Page :n', ['n' => $page]) }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())
        <a class="btn h-9 px-3" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next page') }}">›</a>
    @else
        <span class="btn h-9 px-3 opacity-50" aria-disabled="true" aria-label="{{ __('Next page') }}">›</span>
    @endif
</nav>
@endif
