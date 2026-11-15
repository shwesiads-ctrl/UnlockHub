{{-- Popup content (hero + blocks). Used by <x-popup> and the admin live preview. Needs $popup. --}}
@php
    $theme = \App\Support\Popup::theme($popup['theme']);
    $accent = $theme['accent'];
    $center = $popup['align'] === 'center';
    $tones = [
        'info' => ['box' => 'bg-brand-soft border-brand/30', 'title' => 'text-brand-ink'],
        'success' => ['box' => 'bg-ok-soft border-ok/30', 'title' => 'text-ok'],
        'warning' => ['box' => 'bg-warn-soft border-warn/40 text-center', 'title' => 'text-warn'],
        'danger' => ['box' => 'bg-danger-soft border-danger/30 border-l-4 border-l-danger', 'title' => 'text-danger'],
        'amber' => ['box' => 'bg-point-soft border-point/40', 'title' => 'text-point'],
        'plain' => ['box' => 'bg-surface-2 border-line', 'title' => 'text-ink'],
        'dark' => ['box' => 'bg-[#0F172A] border-[#0F172A] text-white', 'title' => 'text-white'],
    ];
    $tableTones = ['green' => 'bg-ok text-white', 'red' => 'bg-danger text-white', 'amber' => 'bg-warn text-white', 'gray' => 'bg-muted text-white'];
    $marks = ['bullet' => '•', 'check' => '✅', 'cross' => '❌'];
@endphp
<div class="rounded-2xl overflow-hidden border border-line bg-surface shadow-[0_8px_30px_rgb(0_0_0/0.08)] text-sm leading-relaxed">
    @if ($theme['from'] && ($popup['title'] !== '' || $popup['subtitle'] !== '' || $popup['badge'] !== ''))
        <div @class(['px-6 py-7 text-white flex flex-col gap-2', 'items-center text-center' => $center])
             style="background: linear-gradient(135deg, {{ $theme['from'] }}, {{ $theme['to'] }}); border-bottom: 4px solid rgb(0 0 0 / .25)">
            @if ($popup['badge'] !== '')
                <span class="self-auto inline-block w-fit rounded-full bg-[#22C55E] px-3 py-0.5 text-[11px] font-bold tracking-wide">{{ $popup['badge'] }}</span>
            @endif
            @if ($popup['title'] !== '')
                <h2 class="m-0 font-display text-[22px] sm:text-[26px] font-bold leading-tight text-white">{{ \App\Support\Popup::inline($popup['title']) }}</h2>
            @endif
            @if ($popup['subtitle'] !== '')
                <p class="m-0 text-[13px] text-white/85">{{ \App\Support\Popup::inline($popup['subtitle']) }}</p>
            @endif
        </div>
    @endif

    <div class="p-5 sm:p-6 flex flex-col gap-4">
        @foreach (\App\Support\Popup::groups($popup['blocks']) as $group)
            <div @class(['grid gap-4 sm:grid-cols-2' => $group['half'], 'flex flex-col gap-4' => ! $group['half']])>
            @foreach ($group['blocks'] as $b)
                @switch ($b['type'])
                    @case('heading')
                        <h3 class="m-0 pb-1.5 border-b-2 text-lg font-semibold" style="color: {{ $accent }}; border-color: {{ $accent }}">{{ $b['text'] }}</h3>
                        @break

                    @case('text')
                        <p class="m-0">{{ \App\Support\Popup::inline($b['text']) }}</p>
                        @break

                    @case('list')
                        <ul @class(['m-0 flex flex-col gap-1.5', 'pl-5 list-disc' => $b['style'] === 'bullet', 'pl-5 list-decimal' => $b['style'] === 'number', 'pl-0 list-none' => in_array($b['style'], ['check', 'cross'])])>
                            @foreach ($b['items'] as $item)
                                <li @class(['flex gap-2' => in_array($b['style'], ['check', 'cross']), 'font-semibold text-danger' => $b['style'] === 'cross'])>
                                    @if (in_array($b['style'], ['check', 'cross'])) <span aria-hidden="true">{{ $marks[$b['style']] }}</span> @endif
                                    <span>{{ \App\Support\Popup::inline($item) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @break

                    @case('box')
                        @php $t = $tones[$b['tone']]; @endphp
                        <div class="rounded-xl border p-4 flex flex-col gap-2 {{ $t['box'] }}">
                            @if ($b['title'] !== '') <span class="font-bold text-[13px] tracking-wide {{ $t['title'] }}">{{ \App\Support\Popup::inline($b['title']) }}</span> @endif
                            @if ($b['text'] !== '') <p class="m-0 text-[13px]">{{ \App\Support\Popup::inline($b['text']) }}</p> @endif
                            @if ($b['items'])
                                <ul class="m-0 pl-0 list-none flex flex-col gap-1.5 text-[13px]">
                                    @foreach ($b['items'] as $item)
                                        <li class="flex gap-2">
                                            @unless (\App\Support\Popup::hasMarker($item)) <span aria-hidden="true" class="{{ $t['title'] }}">•</span> @endunless
                                            <span>{{ \App\Support\Popup::inline($item) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($b['link_label'] !== '')
                                @if ($b['link_url'] !== '')
                                    <a href="{{ $b['link_url'] }}" target="_blank" rel="noopener" class="font-semibold text-[13px] text-danger w-fit">{{ $b['link_label'] }}</a>
                                @else
                                    <span class="font-semibold text-[13px]">{{ $b['link_label'] }}</span>
                                @endif
                            @endif
                        </div>
                        @break

                    @case('steps')
                        <div class="flex flex-col gap-3">
                            @if ($b['title'] !== '') <span class="w-fit rounded-md bg-[#111827] text-white px-3 py-1 text-[11px] font-bold tracking-wide">{{ $b['title'] }}</span> @endif
                            <ol class="m-0 p-0 list-none rounded-xl border border-line bg-surface-2 divide-y divide-line">
                                @foreach ($b['items'] as $s)
                                    <li class="px-5 py-3.5 flex flex-col gap-1">
                                        @if ($s['title'] !== '') <span class="font-bold text-xs tracking-wide" style="color: {{ $accent }}">{{ $s['title'] }}</span> @endif
                                        @if ($s['text'] !== '') <span>{{ \App\Support\Popup::inline($s['text']) }}</span> @endif
                                        @if ($s['link_label'] !== '' && $s['link_url'] !== '')
                                            <a href="{{ $s['link_url'] }}" target="_blank" rel="noopener" class="font-semibold w-fit" style="color: {{ $accent }}">{{ $s['link_label'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                        @break

                    @case('chips')
                        <div class="rounded-xl border border-line p-4 flex flex-col gap-3">
                            @if ($b['title'] !== '') <span class="font-bold text-[13px]" style="color: {{ $accent }}">{{ $b['title'] }}</span> @endif
                            <div class="grid gap-2 grid-cols-[repeat(auto-fill,minmax(130px,1fr))]">
                                @foreach ($b['items'] as $chip)
                                    <span class="rounded-md border border-line bg-surface-2 px-2 py-1.5 text-center text-xs">{{ $chip }}</span>
                                @endforeach
                            </div>
                        </div>
                        @break

                    @case('table')
                        <div class="rounded-xl border border-line p-4 flex flex-col gap-2">
                            @if ($b['title'] !== '') <span class="font-bold text-xs tracking-wide" style="color: {{ $accent }}">{{ $b['title'] }}</span> @endif
                            <dl class="m-0 rounded-lg border border-dashed border-line divide-y divide-dashed divide-line">
                                @foreach ($b['rows'] as $r)
                                    <div class="flex justify-between gap-3 px-3 py-2 text-[13px]">
                                        <dt class="font-semibold text-muted">{{ $r['key'] }}</dt>
                                        <dd class="m-0 text-right">
                                            @if ($r['tone']) <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tableTones[$r['tone']] }}">{{ $r['value'] }}</span>
                                            @else <span class="font-semibold">{{ $r['value'] }}</span> @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                        @break

                    @case('buttons')
                        <div class="grid gap-3" style="grid-template-columns: repeat({{ count($b['items']) }}, minmax(0, 1fr))">
                            @foreach ($b['items'] as $btn)
                                @php $bg = \App\Support\Popup::BUTTON_COLORS[$btn['color']]; @endphp
                                @if ($btn['url'] === '#order')
                                    <button type="button" data-popup-order class="btn btn-lg border-0 text-white font-bold hover:text-white hover:opacity-90" style="background: {{ $bg }}">{{ $btn['label'] }}</button>
                                @elseif ($btn['url'] !== '')
                                    <a href="{{ $btn['url'] }}" @if (! str_starts_with($btn['url'], '/')) target="_blank" rel="noopener" @endif class="btn btn-lg border-0 text-white font-bold no-underline hover:text-white hover:opacity-90" style="background: {{ $bg }}">{{ $btn['label'] }}</a>
                                @else
                                    <span class="btn btn-lg border-0 text-white font-bold cursor-default" style="background: {{ $bg }}">{{ $btn['label'] }}</span>
                                @endif
                            @endforeach
                        </div>
                        @break
                @endswitch
            @endforeach
            </div>
        @endforeach

        @if ($popup['footer'] !== '')
            <p class="m-0 -mx-5 sm:-mx-6 -mb-5 sm:-mb-6 mt-2 px-6 py-4 border-t border-line bg-surface-2 text-center text-[11px] font-bold tracking-wider text-muted">{{ $popup['footer'] }}</p>
        @endif
    </div>
</div>
