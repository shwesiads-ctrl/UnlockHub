@extends('layouts.app')

@section('content')
<section class="max-w-3xl mx-auto px-4 sm:px-8 py-20 text-center">
    <div class="card p-8 sm:p-12 flex flex-col items-center gap-4">
        <span class="badge badge-info">Demo mode</span>
        <h1 class="m-0 text-3xl font-bold">This demo is read-only</h1>
        <p class="m-0 max-w-xl text-muted">{{ $message }}</p>
        <a href="{{ route('home') }}" class="btn btn-primary mt-2">Back to home</a>
    </div>
</section>
@endsection
