@props(['popup', 'id', 'title' => null, 'delivery' => null, 'auto' => true])
{{-- Modal popup. Opens on page load (data-popup-auto) or from any [data-open-popup="{{ id }}"] button. --}}
<dialog id="{{ $id }}" data-popup="{{ $id }}-{{ \App\Support\Popup::hash($popup) }}"
        data-popup-auto="{{ $auto ? 1 : 0 }}" data-frequency="{{ $popup['frequency'] }}" data-delay="{{ $popup['delay'] }}"
        aria-label="{{ $title ?: ($popup['title'] ?: 'Notice') }}"
        class="uh-popup m-auto p-0 w-[min(680px,calc(100vw-20px))] max-h-[92vh] rounded-2xl border-0 bg-surface text-ink shadow-2xl backdrop:bg-black/55">
    <div class="flex flex-col max-h-[92vh]">
        @if ($title)
            <div class="flex items-start gap-3 px-5 py-3.5 text-white bg-[#3B0A5C]">
                <span class="flex-1 font-medium leading-snug">{{ $title }}</span>
                <form method="dialog"><button class="text-white/90 hover:text-white text-xl leading-none px-1" aria-label="Close">&times;</button></form>
            </div>
        @else
            <form method="dialog" class="flex justify-end px-4 pt-3"><button class="text-sm text-ink hover:underline" aria-label="Close">{{ __('Close') }}</button></form>
        @endif
        <div class="overflow-y-auto px-4 sm:px-8 pt-4 pb-6 flex flex-col gap-3">
            @include('popups._card', ['popup' => $popup])
            @if ($delivery)
                <span class="w-fit rounded bg-[#17A2B8] px-2 py-0.5 text-[11px] font-semibold text-white">{{ __('Delivery Time') }} : {{ $delivery }}</span>
            @endif
        </div>
    </div>
</dialog>
