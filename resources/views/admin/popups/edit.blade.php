@extends('layouts.admin', ['title' => 'Popups', 'subtitle' => 'Welcome popup and service popups'])

@section('content')
<form method="POST" action="{{ route('admin.popups.update') }}" class="card p-6 flex flex-col gap-5">
    @csrf @method('PUT')
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="m-0 text-lg font-bold">Welcome popup</h2>
            <p class="m-0 text-[13px] text-muted">Shown when people open the site — rules, contact buttons, payment news.</p>
        </div>
        <button class="btn btn-primary">Save welcome popup</button>
    </div>
    @include('admin.popups._editor', ['popup' => $popup, 'kind' => 'home'])
    <button class="btn btn-primary self-start">Save welcome popup</button>
</form>

<section class="card overflow-hidden">
    <div class="px-5 py-4 flex flex-wrap justify-between items-center gap-2">
        <div>
            <h2 class="m-0 text-lg font-bold">Service popups</h2>
            <p class="m-0 text-[13px] text-muted">Each service has its own popup: Services › Edit › <strong>Service popup</strong>.</p>
        </div>
        <a href="{{ route('admin.services.index') }}" class="btn">All services</a>
    </div>
    <div class="table-wrap">
        <table class="table min-w-[600px]">
            <thead><tr><th>Service</th><th>Group</th><th>Popup</th><th>Opens</th><th></th></tr></thead>
            <tbody>
            @forelse ($services as $s)
                <tr>
                    <td class="font-semibold">{{ $s->name }}</td>
                    <td class="text-muted">{{ $s->category?->name }}</td>
                    <td>@if ($s->popup['enabled'] ?? false) <span class="badge badge-success">On</span> @else <span class="badge bg-surface-2 text-muted">Off</span> @endif</td>
                    <td class="text-muted text-[13px]">{{ ($s->popup['trigger'] ?? 'open') === 'open' ? 'On page open' : 'Button only' }}</td>
                    <td class="text-right"><a href="{{ route('admin.services.edit', $s) }}#popup" class="btn h-9 text-xs">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-8">No service popups yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
{{ $services->links() }}
@endsection
