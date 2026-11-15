@extends('layouts.app')

@section('content')
<section class="max-w-xl mx-auto px-4 sm:px-8 py-16">
    <div class="card p-7 sm:p-9 flex flex-col gap-4">
        <span class="badge badge-success w-fit">Updated</span>
        <h1 class="m-0 text-2xl font-bold">You’re unsubscribed</h1>
        <p class="m-0 text-sm text-muted">Customer email updates are now off for {{ $email ?: 'your account' }}.</p>
        <a href="{{ route('home') }}" class="btn btn-primary self-start">Back to site</a>
    </div>
</section>
@endsection
