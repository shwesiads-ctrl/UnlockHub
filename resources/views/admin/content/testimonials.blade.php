@extends('layouts.admin', ['title' => 'Content', 'subtitle' => 'Testimonials — approve customer reviews before they go public'])

@section('content')
@include('admin.content._tabs')

<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-4">
        <nav aria-label="Filter" class="flex gap-2">
            <a href="{{ route('admin.testimonials.index') }}" @class(['btn h-9 text-[13px]', 'btn-primary' => $pending])>Waiting ({{ $pendingCount }})</a>
            <a href="{{ route('admin.testimonials.index', ['show' => 'approved']) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => ! $pending])>Approved</a>
        </nav>

        @forelse ($testimonials as $t)
            <form method="POST" action="{{ route('admin.testimonials.update', $t) }}" class="card p-5 flex flex-col gap-3">
                @csrf @method('PUT')
                <div class="flex justify-between items-center gap-3 text-sm">
                    <span><strong>{{ $t->name }}</strong> @if ($t->user) <span class="text-muted">({{ $t->user->username }})</span> @endif · <span class="text-point font-semibold">{{ $t->rating }}/5</span></span>
                    <span class="text-xs text-muted">{{ $t->created_at->format('d M Y H:i') }}</span>
                </div>
                <label for="m{{ $t->id }}" class="sr-only">Message</label>
                <textarea id="m{{ $t->id }}" name="message" rows="3" class="input text-sm">{{ $t->message }}</textarea>
                <div class="flex gap-2 items-center">
                    @if ($t->is_approved)
                        <button name="is_approved" value="0" class="btn h-9 text-xs">Hide</button>
                        <button name="is_approved" value="1" class="btn h-9 text-xs">Save text</button>
                    @else
                        <button name="is_approved" value="1" class="btn btn-success h-9 text-xs">Approve</button>
                    @endif
                    <button form="del{{ $t->id }}" class="btn btn-danger h-9 text-xs ml-auto">Delete</button>
                </div>
            </form>
            <form id="del{{ $t->id }}" method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" onsubmit="return confirm('Delete this review?')">@csrf @method('DELETE')</form>
        @empty
            <div class="card p-10 text-center text-muted">{{ $pending ? 'No reviews waiting.' : 'No approved reviews yet.' }}</div>
        @endforelse
        {{ $testimonials->links() }}
    </div>

    <form method="POST" action="{{ route('admin.testimonials.store') }}" class="flex-[1_1_320px] min-w-0 card p-5 flex flex-col gap-3.5">
        @csrf
        <h2 class="m-0 text-lg font-bold">Add a review</h2>
        <p class="m-0 text-xs text-muted">For reviews customers sent you elsewhere (Viber, Facebook…). Only add real ones.</p>
        <div>
            <label for="name" class="label">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" class="input h-11" required>
        </div>
        <div>
            <label for="rating" class="label">Rating</label>
            <select id="rating" name="rating" class="input h-11">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} / 5</option>@endfor</select>
        </div>
        <div>
            <label for="message" class="label">Review</label>
            <textarea id="message" name="message" rows="4" class="input" required>{{ old('message') }}</textarea>
            <x-field-error name="message" />
        </div>
        <button class="btn btn-primary">Add</button>
    </form>
</div>
@endsection
