{{-- Admin live preview: same look as the real popup, without <dialog>. --}}
<div class="rounded-2xl overflow-hidden bg-surface border border-line">
    @if ($title)
        <div class="flex items-start gap-3 px-4 py-3 text-white bg-[#3B0A5C] text-sm"><span class="flex-1 font-medium">{{ $title }}</span><span aria-hidden="true">&times;</span></div>
    @endif
    <div class="p-3 sm:p-5 flex flex-col gap-3">
        @if (! $popup['enabled'])
            <span class="badge badge-pending w-fit">Turned off — customers won't see this</span>
        @endif
        @include('popups._card', ['popup' => $popup])
        @if ($delivery)
            <span class="w-fit rounded bg-[#17A2B8] px-2 py-0.5 text-[11px] font-semibold text-white">Delivery Time : {{ $delivery }}</span>
        @endif
    </div>
</div>
