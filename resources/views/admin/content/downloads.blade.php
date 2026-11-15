@extends('layouts.admin', ['title' => 'Content', 'subtitle' => 'Downloads — files and links shown on /downloads'])

@section('content')
@include('admin.content._tabs')

<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-4">
        @forelse ($downloads as $d)
            <form method="POST" action="{{ route('admin.downloads.update', $d) }}" enctype="multipart/form-data" class="card p-5 flex flex-col gap-3">
                @csrf @method('PUT')
                <div class="flex justify-between items-center gap-3">
                    <span class="text-xs text-muted">{{ number_format($d->download_count) }} downloads · {{ $d->file_path ? 'file: '.basename($d->file_path) : 'link' }}</span>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($d->is_active)>Visible</label>
                </div>
                <div class="grid gap-3 grid-cols-[1fr_8rem]">
                    <div><label for="t{{ $d->id }}" class="label text-xs">Title</label><input id="t{{ $d->id }}" name="title" value="{{ $d->title }}" class="input h-10" required></div>
                    <div><label for="v{{ $d->id }}" class="label text-xs">Version</label><input id="v{{ $d->id }}" name="version" value="{{ $d->version }}" class="input h-10"></div>
                </div>
                <div><label for="d{{ $d->id }}" class="label text-xs">Description</label><input id="d{{ $d->id }}" name="description" value="{{ $d->description }}" class="input h-10"></div>
                <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                    <div><label for="u{{ $d->id }}" class="label text-xs">Link</label><input id="u{{ $d->id }}" name="url" type="url" value="{{ $d->url }}" class="input h-10" placeholder="https://drive.google.com/…"></div>
                    <div><label for="f{{ $d->id }}" class="label text-xs">Replace file</label><input id="f{{ $d->id }}" name="file" type="file" class="text-sm"></div>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-primary h-9 text-xs">Save</button>
                    <button form="del{{ $d->id }}" class="btn btn-danger h-9 text-xs ml-auto">Delete</button>
                </div>
            </form>
            <form id="del{{ $d->id }}" method="POST" action="{{ route('admin.downloads.destroy', $d) }}" onsubmit="return confirm('Delete this download?')">@csrf @method('DELETE')</form>
        @empty
            <div class="card p-10 text-center text-muted">No downloads yet.</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('admin.downloads.store') }}" enctype="multipart/form-data" class="flex-[1_1_320px] min-w-0 card p-5 flex flex-col gap-3.5">
        @csrf
        <h2 class="m-0 text-lg font-bold">Add download</h2>
        <div><label for="title" class="label">Title</label><input id="title" name="title" value="{{ old('title') }}" class="input h-11" required><x-field-error name="title" /></div>
        <div><label for="version" class="label">Version</label><input id="version" name="version" value="{{ old('version') }}" class="input h-11"></div>
        <div><label for="description" class="label">Description</label><input id="description" name="description" value="{{ old('description') }}" class="input h-11"></div>
        <div><label for="url" class="label">Link <span class="font-normal text-muted">(or upload below)</span></label><input id="url" name="url" type="url" value="{{ old('url') }}" class="input h-11"><x-field-error name="url" /></div>
        <div><label for="file" class="label">File <span class="font-normal text-muted">(max 100 MB)</span></label><input id="file" name="file" type="file" class="text-sm"><x-field-error name="file" /></div>
        <button class="btn btn-primary">Add</button>
    </form>
</div>
{{ $downloads->links() }}
@endsection
