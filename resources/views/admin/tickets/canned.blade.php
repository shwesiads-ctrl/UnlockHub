@extends('layouts.admin', ['title' => 'Saved replies', 'subtitle' => 'Tickets / Saved replies'])

@section('content')
<form method="POST" action="{{ route('admin.canned.store') }}" class="card p-5 flex flex-col gap-3 max-w-3xl">
    @csrf
    <h2 class="m-0 text-lg font-bold">Add saved reply</h2>
    <div><label for="title" class="label text-xs">Title</label><input id="title" name="title" maxlength="100" class="input h-10" required placeholder="e.g. Wrong IMEI – no refund"></div>
    <div><label for="body" class="label text-xs">Text — {name} {ticket} {order} are filled in</label><textarea id="body" name="body" rows="4" class="input" required>Hello {name},

</textarea></div>
    <button class="btn btn-primary self-start">Add</button>
</form>

@foreach ($replies as $r)
    <div class="card p-5 flex flex-col gap-3 max-w-3xl">
        <form method="POST" action="{{ route('admin.canned.update', $r) }}" class="flex flex-col gap-3">
            @csrf @method('PUT')
            <label for="t{{ $r->id }}" class="sr-only">Title</label><input id="t{{ $r->id }}" name="title" value="{{ $r->title }}" maxlength="100" class="input h-10 font-semibold">
            <label for="b{{ $r->id }}" class="sr-only">Text</label><textarea id="b{{ $r->id }}" name="body" rows="4" class="input">{{ $r->body }}</textarea>
            <div class="flex gap-2"><button class="btn h-9 text-sm">Save</button></div>
        </form>
        <form method="POST" action="{{ route('admin.canned.destroy', $r) }}" onsubmit="return confirm('Delete this saved reply?')">@csrf @method('DELETE')<button class="btn btn-danger h-9 text-xs">Delete</button></form>
    </div>
@endforeach
@endsection
