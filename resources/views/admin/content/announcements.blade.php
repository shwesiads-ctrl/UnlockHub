@extends('layouts.admin', ['title' => 'Content', 'subtitle' => 'Announcements — the bar shown at the top of every public page'])

@section('content')
@include('admin.content._tabs')

<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-4">
        <p class="m-0 text-sm text-muted">Only the newest active announcement within its dates is shown.</p>
        @forelse ($announcements as $a)
            <form method="POST" action="{{ route('admin.announcements.update', $a) }}" class="card p-5 flex flex-col gap-3">
                @csrf @method('PUT')
                @include('admin.content._announcement-fields', ['a' => $a, 'p' => 'a'.$a->id])
                <div class="flex gap-2">
                    <button class="btn btn-primary h-9 text-xs">Save</button>
                    <button form="del{{ $a->id }}" class="btn btn-danger h-9 text-xs ml-auto">Delete</button>
                </div>
            </form>
            <form id="del{{ $a->id }}" method="POST" action="{{ route('admin.announcements.destroy', $a) }}" onsubmit="return confirm('Delete this announcement?')">@csrf @method('DELETE')</form>
        @empty
            <div class="card p-10 text-center text-muted">No announcements.</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('admin.announcements.store') }}" class="flex-[1_1_320px] min-w-0 card p-5 flex flex-col gap-3.5">
        @csrf
        <h2 class="m-0 text-lg font-bold">New announcement</h2>
        @include('admin.content._announcement-fields', ['a' => new \App\Models\Announcement(['style' => 'info', 'is_active' => true]), 'p' => 'new'])
        <button class="btn btn-primary">Add</button>
    </form>
</div>
@endsection
