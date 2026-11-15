@props(['title', 'subtitle' => null])
<section class="max-w-md mx-auto px-4 py-14">
    <div class="card p-7 sm:p-8 flex flex-col gap-5">
        <div class="flex flex-col gap-1.5">
            <h1 class="m-0 text-[28px] font-bold">{{ $title }}</h1>
            @if ($subtitle) <p class="m-0 text-muted text-[15px] leading-relaxed">{{ $subtitle }}</p> @endif
        </div>
        {{ $slot }}
    </div>
    @isset($footer)
        <p class="text-center text-sm text-muted mt-5">{{ $footer }}</p>
    @endisset
</section>
