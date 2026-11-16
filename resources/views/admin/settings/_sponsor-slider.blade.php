<section id="sponsor-slider" class="settings-page-panel card p-6 flex flex-col gap-5">
    <div class="settings-heading">
        <span class="badge badge-info">Homepage</span>
        <h2>Sponsor image slider</h2>
        <p>Upload up to 8 sponsor banners. They rotate automatically between the payment banner and footer. Add an optional link to make a card clickable.</p>
    </div>
    <form method="POST" action="{{ route('admin.settings.sponsor-slider') }}" enctype="multipart/form-data" class="flex flex-col gap-5">
        @csrf @method('PUT')
        <label class="flex items-center gap-2.5 text-sm font-semibold">
            <input type="checkbox" name="enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('home.sponsor_slides_enabled') === '1')>
            Show sponsor slider on the homepage
        </label>
        <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(360px,100%),1fr))]">
            @for ($i = 0; $i < 8; $i++)
                @php $slide = $sponsorSlides[$i] ?? []; @endphp
                <details @if (!empty($slide['image']) || $i === count($sponsorSlides)) open @endif class="rounded-xl border border-line p-4">
                    <summary class="cursor-pointer font-semibold">Sponsor card {{ $i + 1 }}@if (!empty($slide['title'])) <span class="font-normal text-muted">· {{ $slide['title'] }}</span> @endif</summary>
                    <div class="pt-4 flex flex-col gap-3">
                    @if (!empty($slide['image']))
                        <div class="rounded-lg bg-surface-2 p-2">
                            <img src="{{ Storage::disk('public')->url($slide['image']) }}" alt="{{ $slide['title'] ?: 'Current sponsor image' }}" class="w-full h-28 object-contain">
                        </div>
                        <label class="flex items-center gap-2 text-xs text-danger"><input type="checkbox" name="slides[{{ $i }}][remove]" value="1">Remove this image</label>
                    @endif
                    <div>
                        <label for="sponsor_image_{{ $i }}" class="label text-xs">{{ !empty($slide['image']) ? 'Replace image' : 'Sponsor image' }} <span class="font-normal text-muted">(PNG/JPG/WebP, max 4 MB)</span></label>
                        <input id="sponsor_image_{{ $i }}" name="slides[{{ $i }}][image]" type="file" accept="image/png,image/jpeg,image/webp" class="text-sm">
                        <x-field-error :name="'slides.'.$i.'.image'" />
                    </div>
                    <div>
                        <label for="sponsor_title_{{ $i }}" class="label text-xs">Card title / alt text <span class="font-normal text-muted">(optional)</span></label>
                        <input id="sponsor_title_{{ $i }}" name="slides[{{ $i }}][title]" value="{{ old('slides.'.$i.'.title', $slide['title'] ?? '') }}" maxlength="100" class="input h-10">
                    </div>
                    <div>
                        <label for="sponsor_link_{{ $i }}" class="label text-xs">Link <span class="font-normal text-muted">(optional, http/https)</span></label>
                        <input id="sponsor_link_{{ $i }}" name="slides[{{ $i }}][link]" type="url" value="{{ old('slides.'.$i.'.link', $slide['link'] ?? '') }}" maxlength="255" class="input h-10" placeholder="https://…">
                        <x-field-error :name="'slides.'.$i.'.link'" />
                    </div>
                    </div>
                </details>
            @endfor
        </div>
        <div class="settings-actions">
            <span>Images rotate every 5 seconds. Each card pauses while hovered or focused.</span>
            <button class="btn btn-primary">Save sponsor slider</button>
        </div>
    </form>
</section>
