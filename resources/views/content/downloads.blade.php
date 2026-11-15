@extends('layouts.app', ['title' => 'Downloads'])

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-10 flex flex-col gap-6">
    <div class="flex flex-col gap-1.5">
        <h1 class="m-0 text-[34px] font-bold">{{ __('Downloads') }}</h1>
        <p class="m-0 text-muted text-[15px]">{{ __('Drivers, tool setups and files you need for our services.') }}</p>
    </div>

    @if ($downloads->isEmpty())
        <div class="card p-10 text-center text-muted">{{ __('No downloads yet.') }}</div>
    @else
        <div class="card table-wrap">
            <table class="table min-w-[620px]">
                <thead><tr><th>{{ __('File') }}</th><th>{{ __('Version') }}</th><th>{{ __('Updated') }}</th><th><span class="sr-only">{{ __('Download') }}</span></th></tr></thead>
                <tbody>
                @foreach ($downloads as $d)
                    <tr>
                        <td><div class="font-semibold">{{ $d->title }}</div>@if ($d->description)<div class="text-[13px] text-muted mt-0.5">{{ $d->description }}</div>@endif</td>
                        <td class="text-muted whitespace-nowrap">{{ $d->version ?: '—' }}</td>
                        <td class="text-muted whitespace-nowrap">{{ $d->updated_at->format('d M Y') }}</td>
                        <td class="text-right"><a href="{{ route('downloads.get', $d) }}" class="btn btn-primary h-10" rel="nofollow"><x-icon name="upload" size="16" class="rotate-180" />{{ __('Download') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
{{ $downloads->links() }}
@endsection
