{{--
    Popup builder. Params:
      $popup     current popup array (or null)
      $kind      'service' | 'home'
      $barTitle  title shown in the popup's top bar (service name), optional
      $copyFrom  collection of services that already have a popup (service form only), optional
--}}
@php
    $state = $popup ?? \App\Support\Popup::sanitize(['enabled' => false, 'blocks' => []]);
    $templates = \App\Support\PopupTemplates::all();
@endphp
<div data-popup-editor data-preview-url="{{ route('admin.popups.preview') }}" data-bar-title="{{ $barTitle ?? '' }}" class="flex flex-wrap gap-5 items-start">
    <input type="hidden" name="popup" value="{{ json_encode($state) }}" data-popup-json>
    <script type="application/json" data-templates>@json($templates)</script>

    <div class="flex-[999_1_460px] min-w-0 flex flex-col gap-4">
        {{-- When / who --}}
        <div class="rounded-xl bg-surface-2 p-4 flex flex-col gap-3">
            <label class="flex items-center gap-2.5 font-semibold">
                <input type="checkbox" data-top="enabled" class="w-5 h-5 accent-[var(--uh-brand)]">
                {{ $kind === 'home' ? 'Show the welcome popup' : 'Show a popup for this service' }}
            </label>
            <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                @if ($kind === 'service')
                    <div>
                        <label for="pp_trigger" class="label text-xs">Opens</label>
                        <select id="pp_trigger" data-top="trigger" class="input h-10">
                            <option value="open">When the service page opens</option>
                            <option value="button">Only from the "Service info" button</option>
                        </select>
                    </div>
                @else
                    <div>
                        <label for="pp_audience" class="label text-xs">Who sees it</label>
                        <select id="pp_audience" data-top="audience" class="input h-10">
                            <option value="all">Everyone</option>
                            <option value="guests">Guests only (not logged in)</option>
                            <option value="users">Logged-in users only</option>
                        </select>
                    </div>
                    <div>
                        <label for="pp_pages" class="label text-xs">Where</label>
                        <select id="pp_pages" data-top="pages" class="input h-10">
                            <option value="home">Home page only</option>
                            <option value="all">Every public page</option>
                        </select>
                    </div>
                    <div>
                        <label for="pp_delay" class="label text-xs">Delay (seconds)</label>
                        <input id="pp_delay" data-top="delay" type="number" min="0" max="30" class="input h-10">
                    </div>
                @endif
                <div>
                    <label for="pp_frequency" class="label text-xs">How often</label>
                    <select id="pp_frequency" data-top="frequency" class="input h-10">
                        <option value="session">Once per visit (browser session)</option>
                        <option value="daily">Once a day</option>
                        <option value="once">Only once (until you edit it)</option>
                        <option value="always">Every time</option>
                    </select>
                </div>
            </div>
            <p class="m-0 text-xs text-muted">After you edit and save the popup, everyone sees it again once.</p>
        </div>

        {{-- Template / copy --}}
        <div class="flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-48">
                <label for="pp_template" class="label text-xs">Start from a template</label>
                <select id="pp_template" data-template-select class="input h-10">
                    @foreach ($templates as $key => $t) <option value="{{ $key }}">{{ $t['label'] }}</option> @endforeach
                </select>
            </div>
            <button type="button" class="btn h-10" data-load-template>Use template</button>
            @if (! empty($copyFrom) && count($copyFrom))
                <div class="flex-1 min-w-48">
                    <label for="pp_copy" class="label text-xs">…or copy from a service</label>
                    <select id="pp_copy" data-copy-select class="input h-10">
                        <option value="">Select…</option>
                        @foreach ($copyFrom as $s) <option value="{{ route('admin.popups.source', $s) }}">{{ \Illuminate\Support\Str::limit($s->name, 60) }}</option> @endforeach
                    </select>
                </div>
                <button type="button" class="btn h-10" data-copy-from>Copy</button>
            @endif
        </div>

        {{-- Header --}}
        <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
            <div>
                <label for="pp_theme" class="label text-xs">Header colour</label>
                <select id="pp_theme" data-top="theme" class="input h-10">
                    @foreach (\App\Support\Popup::THEMES as $k => $t) <option value="{{ $k }}">{{ $t['label'] }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="pp_align" class="label text-xs">Header text</label>
                <select id="pp_align" data-top="align" class="input h-10">
                    <option value="center">Centered</option>
                    <option value="left">Left</option>
                </select>
            </div>
            <div>
                <label for="pp_badge" class="label text-xs">Badge (small green pill)</label>
                <input id="pp_badge" data-top="badge" class="input h-10" maxlength="60" placeholder="NEW UPDATE">
            </div>
            <div class="col-span-full">
                <label for="pp_title" class="label text-xs">Title</label>
                <input id="pp_title" data-top="title" class="input h-10" maxlength="200">
            </div>
            <div class="col-span-full">
                <label for="pp_subtitle" class="label text-xs">Subtitle</label>
                <input id="pp_subtitle" data-top="subtitle" class="input h-10" maxlength="300">
            </div>
        </div>

        {{-- Blocks --}}
        <div class="flex flex-col gap-3" data-blocks></div>

        <div class="flex flex-wrap gap-2 items-center">
            <label for="pp_add" class="sr-only">Block type</label>
            <select id="pp_add" data-block-type class="input h-10 w-auto">
                <option value="heading">Section heading</option>
                <option value="text">Paragraph</option>
                <option value="list">List</option>
                <option value="box" selected>Coloured box</option>
                <option value="steps">Steps</option>
                <option value="chips">Chips grid</option>
                <option value="table">Info table</option>
                <option value="buttons">Buttons</option>
            </select>
            <button type="button" class="btn h-10 text-brand-ink" data-add-block>+ Add block</button>
        </div>

        <div>
            <label for="pp_footer" class="label text-xs">Footer line (optional)</label>
            <input id="pp_footer" data-top="footer" class="input h-10" maxlength="200" placeholder="OFFICIAL AUTOMATED API PROCESSING HUB">
        </div>

        <p class="m-0 text-xs text-muted leading-relaxed">
            Tips: <code>**text**</code> makes text <strong>bold</strong>. <code>{name}</code> = user's name (or "Guest"), <code>{site}</code> = site name.
            Emoji work everywhere (✅ ❌ ⚠️ 🌍).
        </p>
    </div>

    {{-- Live preview --}}
    <div class="flex-[1_1_360px] min-w-0 lg:sticky lg:top-4 flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <span class="text-sm font-bold">Live preview</span>
            <button type="button" class="btn h-8 text-xs" data-preview-open>Open full size</button>
        </div>
        <div data-preview class="rounded-xl border border-line bg-bg p-3 max-h-[75vh] overflow-y-auto">
            <p class="m-0 text-sm text-muted">Loading…</p>
        </div>
    </div>

    <dialog id="popup-preview-dialog" class="m-auto p-0 w-[min(680px,calc(100vw-20px))] max-h-[92vh] rounded-2xl border-0 bg-surface text-ink shadow-2xl backdrop:bg-black/55" data-popup="preview" data-popup-auto="0">
        <div class="flex justify-end px-4 pt-3"><button type="button" class="btn h-8 text-xs" data-close-dialog>Close preview</button></div>
        <div data-preview-full class="px-3 pb-4"></div>
    </dialog>
</div>
