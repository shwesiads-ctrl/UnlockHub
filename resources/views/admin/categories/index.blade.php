@extends('layouts.admin', ['title' => 'Service Groups', 'subtitle' => 'Groups shown on the pricing page (Samsung, iPhone · USA Carrier, Tool Activations…)'])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
        @foreach (\App\Models\Service::TYPES as $type => $label)
            <section class="card overflow-hidden">
                <h2 class="m-0 px-5 py-4 text-lg font-bold">{{ $label }}</h2>
                <div class="table-wrap">
                    <table class="table min-w-[600px]">
                        <thead><tr><th>Name</th><th class="w-24">Sort</th><th class="w-24">Active</th><th class="w-24">Services</th><th class="w-44"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                        @forelse ($categories[$type] ?? [] as $cat)
                            <tr>
                                <td colspan="3" class="p-0">
                                    <form id="cat-{{ $cat->id }}" method="POST" action="{{ route('admin.categories.update', $cat) }}" class="grid grid-cols-[1fr_6rem_6rem] items-center">
                                        @csrf @method('PUT')
                                        <div class="px-4 py-2.5"><label for="cn-{{ $cat->id }}" class="sr-only">Name</label><input id="cn-{{ $cat->id }}" name="name" value="{{ $cat->name }}" class="input h-10"></div>
                                        <div class="px-4 py-2.5"><label for="cs-{{ $cat->id }}" class="sr-only">Sort</label><input id="cs-{{ $cat->id }}" name="sort" type="number" min="0" value="{{ $cat->sort }}" class="input h-10"></div>
                                        <div class="px-4 py-2.5"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($cat->is_active)><span class="sr-only">Active</span></label></div>
                                    </form>
                                </td>
                                <td><a href="{{ route('admin.services.index', ['category' => $cat->id]) }}">{{ $cat->services_count }}</a></td>
                                <td class="whitespace-nowrap">
                                    <button form="cat-{{ $cat->id }}" class="btn h-9 text-xs">Save</button>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger h-9 text-xs" @disabled($cat->services_count > 0) title="{{ $cat->services_count ? 'Has services' : 'Delete' }}">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No groups yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    <aside class="flex-[1_1_320px] min-w-0">
        <form method="POST" action="{{ route('admin.categories.store') }}" class="card p-5 flex flex-col gap-4">
            @csrf
            <h2 class="m-0 text-lg font-bold">Add group</h2>
            <div>
                <label for="type" class="label">Service type</label>
                <select id="type" name="type" class="input">
                    @foreach (\App\Models\Service::TYPES as $k => $l)
                        <option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="name" class="label">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" class="input" placeholder="e.g. Xiaomi" required>
                <x-field-error name="name" />
            </div>
            <div>
                <label for="sort" class="label">Sort order</label>
                <input id="sort" name="sort" type="number" min="0" value="{{ old('sort', 0) }}" class="input">
            </div>
            <button class="btn btn-primary">Add group</button>
        </form>
    </aside>
</div>
@endsection
