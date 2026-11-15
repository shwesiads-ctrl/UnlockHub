@php
    $paymentLogos = json_decode(setting('footer.payment_logos', '[]'), true) ?: [];
    $copyright    = setting('footer.copyright', '© '.date('Y').' '.setting('site.name', config('app.name')).' All rights reserved');
@endphp

<section id="footer-settings" class="settings-page-panel card p-6 flex flex-col gap-5">
    <div class="settings-heading">
        <span class="badge badge-info">Footer</span>
        <h2>Footer — copyright &amp; payment logos</h2>
        <p>These show in the site footer visible to all visitors.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.footer') }}" enctype="multipart/form-data"
          class="flex flex-col gap-5">
        @csrf @method('PUT')

        {{-- Copyright text --}}
        <div>
            <label for="footer_copyright" class="label">Copyright text</label>
            <input id="footer_copyright" name="footer_copyright"
                   value="{{ old('footer_copyright', $copyright) }}"
                   placeholder="© 2026 Your Shop All rights reserved"
                   class="input h-11" maxlength="200">
            <p class="mt-1 text-xs text-muted">Shown at the bottom-left of the footer.</p>
            <x-field-error name="footer_copyright" />
        </div>

        {{-- Existing logos --}}
        @if (count($paymentLogos))
        <div class="flex flex-col gap-3">
            <span class="label mb-0">Current payment logos</span>
            <div class="flex flex-wrap gap-3">
                @foreach ($paymentLogos as $i => $path)
                <div class="settings-subpanel flex-row items-center gap-3 !flex !py-2.5 !px-3">
                    <img src="{{ Storage::disk('public')->url($path) }}"
                         alt="Logo {{ $i + 1 }}"
                         class="h-8 w-auto max-w-[90px] object-contain">
                    <form method="POST"
                          action="{{ route('admin.settings.footer.logo.remove', $i) }}"
                          onsubmit="return confirm('Remove this logo?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="btn btn-danger !h-8 !px-3 text-xs">Remove</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Upload new logos --}}
        <div class="settings-upload flex flex-col gap-2">
            <label class="label mb-0">
                Add payment logos
                <span class="font-normal text-muted">(PNG/JPG/WebP, transparent, up to 8 total — Visa, Mastercard, PayPal …)</span>
            </label>
            <input name="footer_logos[]" type="file"
                   accept="image/png,image/jpeg,image/webp"
                   multiple class="text-sm">
            <p class="m-0 text-xs text-muted">Select multiple files at once. Each file max 512 KB.</p>
            <x-field-error name="footer_logos" />
            <x-field-error name="footer_logos.*" />
        </div>

        <div class="settings-actions">
            <button class="btn btn-primary">Save footer settings</button>
        </div>
    </form>
</section>
