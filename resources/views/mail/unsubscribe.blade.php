@extends('layouts.app')

@section('content')
<section class="max-w-xl mx-auto px-4 sm:px-8 py-16">
    <div class="card p-7 sm:p-9 flex flex-col gap-4">
        <span class="badge badge-info w-fit">Email preferences</span>
        <h1 class="m-0 text-2xl font-bold">{{ $alreadyUnsubscribed ? 'Email updates are already off' : 'Stop customer emails?' }}</h1>
        <p class="m-0 text-sm text-muted">{{ $alreadyUnsubscribed ? 'No action is needed.' : 'This will turn off customer email updates for '.($email ?: 'your account').'. Security and sign-in emails may still be sent.' }}</p>
        @unless ($alreadyUnsubscribed)
            <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-wrap gap-2">
                @csrf
                <button class="btn btn-primary">Unsubscribe</button>
                <a href="{{ route('home') }}" class="btn">Keep email updates</a>
            </form>
        @endunless
    </div>
</section>
@endsection
