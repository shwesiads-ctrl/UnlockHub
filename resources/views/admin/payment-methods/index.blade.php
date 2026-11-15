@extends('layouts.admin', ['title' => 'Payment Methods', 'subtitle' => 'The accounts users transfer to. Turn a method on after adding its account number.'])

@section('content')
<div class="grid gap-5 grid-cols-[repeat(auto-fit,minmax(420px,1fr))]">
    @foreach ($methods as $m)
        <form method="POST" action="{{ route('admin.payment-methods.update', $m) }}" enctype="multipart/form-data" class="card p-5 flex flex-col gap-4">
            @csrf @method('PUT')
            <div class="flex justify-between items-center gap-3">
                <h2 class="m-0 text-lg font-bold">{{ $m->name }}</h2>
                <div class="flex items-center gap-2">
                    @if ($m->pending_count) <span class="badge badge-pending">{{ $m->pending_count }} pending</span> @endif
                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" name="is_active" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($m->is_active)>
                        On
                    </label>
                </div>
            </div>
            <div class="grid gap-3 grid-cols-2">
                <div>
                    <label for="n{{ $m->id }}" class="label text-xs">Display name</label>
                    <input id="n{{ $m->id }}" name="name" value="{{ $m->name }}" class="input h-10" required>
                </div>
                <div>
                    <label for="s{{ $m->id }}" class="label text-xs">Sort</label>
                    <input id="s{{ $m->id }}" name="sort" type="number" min="0" value="{{ $m->sort }}" class="input h-10">
                </div>
                <div>
                    <label for="an{{ $m->id }}" class="label text-xs">Account name</label>
                    <input id="an{{ $m->id }}" name="account_name" value="{{ $m->account_name }}" class="input h-10">
                </div>
                <div>
                    <label for="ac{{ $m->id }}" class="label text-xs">Account / phone number</label>
                    <input id="ac{{ $m->id }}" name="account_number" value="{{ $m->account_number }}" class="input h-10 font-mono">
                    <x-field-error :name="'account_number_'.$m->id" />
                </div>
                <div>
                    <label for="mi{{ $m->id }}" class="label text-xs">Min amount (Ks)</label>
                    <input id="mi{{ $m->id }}" name="min_amount" type="number" min="0" value="{{ (float) $m->min_amount }}" class="input h-10">
                </div>
                <div>
                    <label for="ma{{ $m->id }}" class="label text-xs">Max amount (Ks) <span class="font-normal text-muted">optional</span></label>
                    <input id="ma{{ $m->id }}" name="max_amount" type="number" min="0" value="{{ $m->max_amount ? (float) $m->max_amount : '' }}" class="input h-10">
                </div>
            </div>
            <div>
                <label for="in{{ $m->id }}" class="label text-xs">Instructions shown to users <span class="font-normal text-muted">(:username is replaced)</span></label>
                <textarea id="in{{ $m->id }}" name="instructions" rows="2" class="input text-sm">{{ $m->instructions }}</textarea>
            </div>
            <div class="flex items-center gap-4">
                @if ($m->qr_path)
                    <img src="{{ Storage::disk('public')->url($m->qr_path) }}" alt="{{ $m->name }} QR" class="w-20 h-20 rounded-lg bg-white p-1 object-contain">
                @endif
                <div class="flex-1 flex flex-col gap-1.5">
                    <label for="qr{{ $m->id }}" class="label text-xs mb-0">QR code image</label>
                    <input id="qr{{ $m->id }}" name="qr" type="file" accept="image/*" class="text-sm">
                    @if ($m->qr_path)
                        <label class="flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_qr" value="1">Remove current QR</label>
                    @endif
                </div>
            </div>
            <button class="btn btn-primary self-start">Save {{ $m->name }}</button>
        </form>
    @endforeach

    <form method="POST" action="{{ route('admin.payment-methods.store') }}" class="card p-5 flex flex-col gap-3 border-dashed">
        @csrf
        <h2 class="m-0 text-lg font-bold">Add another method</h2>
        <div>
            <label for="new_name" class="label">Name</label>
            <input id="new_name" name="name" class="input" placeholder="e.g. UAB Pay, Bank transfer" required>
            <x-field-error name="name" />
        </div>
        <button class="btn self-start">Add method</button>
    </form>
</div>
{{ $methods->links() }}
@endsection
