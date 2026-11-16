@php
    $home = fn (string $key, string $default = '') => old($key, setting('home.'.$key, $default));
    $homeToggle = fn (string $key, string $default = '1') => old($key, setting('home.'.$key, $default)) === '1';
@endphp
<form id="home-page" method="POST" action="{{ route('admin.settings.home-page') }}" class="settings-page-panel card p-6 flex flex-col gap-5">
    @csrf @method('PUT')
    <div class="settings-heading">
        <span class="badge badge-info">Homepage</span>
        <h2>Home page content</h2>
        <p>Edit the text and sections displayed on the public homepage. Use <code>:methods</code> in the hero description to insert the active payment methods.</p>
    </div>

    <section class="settings-section">
        <h3>Hero</h3>
        <div class="settings-fields">
            <div><label for="home_hero_badge" class="label">Small badge</label><input id="home_hero_badge" name="hero_badge" value="{{ $home('hero_badge', 'For repair shops & resellers in Myanmar') }}" maxlength="100" class="input h-11"><x-field-error name="hero_badge" /></div>
            <div><label for="home_browse_label" class="label">Browse services button</label><input id="home_browse_label" name="browse_label" value="{{ $home('browse_label', 'Browse services') }}" maxlength="50" class="input h-11" required><x-field-error name="browse_label" /></div>
            <div class="sm:col-span-2"><label for="home_hero_title" class="label">Main headline</label><input id="home_hero_title" name="hero_title" value="{{ $home('hero_title', 'Unlock codes, tool credits & activations — in one wallet.') }}" maxlength="180" class="input h-11" required><x-field-error name="hero_title" /></div>
            <div class="sm:col-span-2"><label for="home_hero_description" class="label">Description</label><textarea id="home_hero_description" name="hero_description" rows="3" maxlength="700" class="input" required>{{ $home('hero_description', 'Top up with :methods, place IMEI and server orders, track every result, and earn points on each successful order.') }}</textarea><x-field-error name="hero_description" /></div>
            <div class="sm:col-span-2 grid sm:grid-cols-3 gap-3">
                @foreach ([1 => 'Auto refund on rejected orders', 2 => 'Reseller group pricing', 3 => '2FA secured accounts'] as $i => $default)
                    <div><label for="home_trust_{{ $i }}" class="label">Trust point {{ $i }}</label><input id="home_trust_{{ $i }}" name="trust_{{ $i }}" value="{{ $home('trust_'.$i, $default) }}" maxlength="80" class="input h-10"><x-field-error :name="'trust_'.$i" /></div>
                @endforeach
            </div>
            <label class="sm:col-span-2 flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="show_reseller_cta" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($homeToggle('show_reseller_cta', setting('home.reseller_cta_enabled', '1')))><span>Show the Become a reseller button</span></label>
            <div><label for="home_reseller_cta_label" class="label">Reseller button label</label><input id="home_reseller_cta_label" name="reseller_cta_label" value="{{ $home('reseller_cta_label', 'Become a reseller') }}" maxlength="50" class="input h-10" required><x-field-error name="reseller_cta_label" /></div>
        </div>
    </section>

    <details class="settings-section rounded-xl border border-line p-4">
        <summary class="cursor-pointer font-bold">Service categories</summary>
        <div class="pt-4 flex flex-col gap-4">
            <label class="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="show_categories" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($homeToggle('show_categories'))>Show this section</label>
            <div class="settings-fields">
                <div><label for="home_categories_title" class="label">Section title</label><input id="home_categories_title" name="categories_title" value="{{ $home('categories_title', 'Service categories') }}" maxlength="80" class="input h-10" required><x-field-error name="categories_title" /></div>
                @foreach ([
                    'imei' => ['IMEI / Serial Services', 'Carrier unlocks, NCK/MCK codes, IMEI & GSX checks. Submit IMEI, SN or ECID.'],
                    'server' => ['Server · Box · Tool Credits', 'UnlockTool, Chimera, TFM, dongle renewals, game credits and gift cards.'],
                    'remote' => ['Remote · Rent · Files', 'Live remote sessions, hourly tool rental and paid firmware files.'],
                ] as $type => [$title, $description])
                    <div><label for="home_category_{{ $type }}_title" class="label">{{ ucfirst($type) }} title</label><input id="home_category_{{ $type }}_title" name="category_{{ $type }}_title" value="{{ $home('category_'.$type.'_title', $title) }}" maxlength="100" class="input h-10" required><x-field-error :name="'category_'.$type.'_title'" /></div>
                    <div><label for="home_category_{{ $type }}_description" class="label">{{ ucfirst($type) }} description</label><textarea id="home_category_{{ $type }}_description" name="category_{{ $type }}_description" rows="2" maxlength="300" class="input" required>{{ $home('category_'.$type.'_description', $description) }}</textarea><x-field-error :name="'category_'.$type.'_description'" /></div>
                @endforeach
            </div>
        </div>
    </details>

    <details class="settings-section rounded-xl border border-line p-4">
        <summary class="cursor-pointer font-bold">Hot services</summary>
        <div class="pt-4 flex flex-col gap-3">
            <label class="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="show_hot_services" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($homeToggle('show_hot_services'))>Show this section</label>
            <div><label for="home_hot_title" class="label">Section title</label><input id="home_hot_title" name="hot_title" value="{{ $home('hot_title', 'Hot services') }}" maxlength="80" class="input h-10" required><x-field-error name="hot_title" /></div>
        </div>
    </details>

    <details class="settings-section rounded-xl border border-line p-4">
        <summary class="cursor-pointer font-bold">How it works</summary>
        <div class="pt-4 flex flex-col gap-4">
            <label class="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="show_how_it_works" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($homeToggle('show_how_it_works'))>Show this section</label>
            <div><label for="home_how_title" class="label">Section title</label><input id="home_how_title" name="how_title" value="{{ $home('how_title', 'How it works') }}" maxlength="80" class="input h-10" required><x-field-error name="how_title" /></div>
            <div class="settings-fields">
                @foreach ([
                    1 => ['Register & verify', 'Email OTP and optional Google Authenticator.'],
                    2 => ['Add funds', 'Transfer by mobile pay, upload the receipt, admin approves.'],
                    3 => ['Place orders', 'Single or bulk IMEI. Credit is held until the result arrives.'],
                    4 => ['Get result & points', 'Code delivered to your orders page; points added on success.'],
                ] as $i => [$title, $description])
                    <div><label for="home_step_{{ $i }}_title" class="label">Step {{ $i }} title</label><input id="home_step_{{ $i }}_title" name="step_{{ $i }}_title" value="{{ $home('step_'.$i.'_title', $title) }}" maxlength="80" class="input h-10" required><x-field-error :name="'step_'.$i.'_title'" /></div>
                    <div><label for="home_step_{{ $i }}_description" class="label">Step {{ $i }} description</label><textarea id="home_step_{{ $i }}_description" name="step_{{ $i }}_description" rows="2" maxlength="220" class="input" required>{{ $home('step_'.$i.'_description', $description) }}</textarea><x-field-error :name="'step_'.$i.'_description'" /></div>
                @endforeach
            </div>
        </div>
    </details>

    <details class="settings-section rounded-xl border border-line p-4">
        <summary class="cursor-pointer font-bold">Payment banner</summary>
        <div class="pt-4 flex flex-col gap-3">
            <label class="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="show_payment_banner" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($homeToggle('show_payment_banner'))>Show this section</label>
            <div class="settings-fields">
                <div><label for="home_payment_title" class="label">Banner title <span class="font-normal text-muted">(blank uses country-based title)</span></label><input id="home_payment_title" name="payment_title" value="{{ $home('payment_title') }}" maxlength="100" class="input h-10"><x-field-error name="payment_title" /></div>
                <div><label for="home_payment_description" class="label">Banner description</label><textarea id="home_payment_description" name="payment_description" rows="2" maxlength="240" class="input" required>{{ $home('payment_description', 'Transfer, upload your receipt and your wallet is credited after approval.') }}</textarea><x-field-error name="payment_description" /></div>
            </div>
        </div>
    </details>

    <div class="settings-actions">
        <span>Changes appear on the public homepage after saving.</span>
        <button class="btn btn-primary">Save home page settings</button>
    </div>
</form>
