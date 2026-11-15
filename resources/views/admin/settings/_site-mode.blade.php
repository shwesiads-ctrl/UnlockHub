<section id="site-mode" class="settings-page-panel card p-6 flex flex-col gap-5">
    @php
        $modeEnabled = setting('site.maintenance') === '1';
        $modeStarts = setting('site.maintenance_starts_at');
        $modeEnds = setting('site.maintenance_ends_at');
        $modeTimezone = (string) config('unlockhub.timezone', 'Asia/Yangon');
        $modeNow = now($modeTimezone);
        $modeStatus = ! $modeEnabled ? 'Off'
            : ($modeStarts && $modeNow->lt(\Illuminate\Support\Carbon::parse($modeStarts, $modeTimezone)) ? 'Scheduled'
                : ($modeEnds && $modeNow->gte(\Illuminate\Support\Carbon::parse($modeEnds, $modeTimezone)) ? 'Expired' : 'Active'));
        $startInput = $modeStarts ? \Illuminate\Support\Carbon::parse($modeStarts, $modeTimezone)->format('Y-m-d\\TH:i') : '';
        $endInput = $modeEnds ? \Illuminate\Support\Carbon::parse($modeEnds, $modeTimezone)->format('Y-m-d\\TH:i') : '';
    @endphp
    <div class="settings-heading">
        <span class="badge badge-info">Availability</span>
        <div class="flex flex-wrap items-center gap-3"><h2 class="m-0">Demo &amp; maintenance mode</h2><span class="badge {{ $modeStatus === 'Active' ? 'badge-pending' : 'badge-info' }}">Maintenance: {{ $modeStatus }}</span></div>
        <p>Control whether customers can use the site. Administrators can always access the dashboard and change these settings.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.site-mode') }}" class="flex flex-col gap-5">
        @csrf @method('PUT')
        <div class="grid gap-4 md:grid-cols-2">
            <label class="settings-subpanel settings-mode-option gap-3">
                <input type="checkbox" name="demo" value="1" class="mt-1 h-5 w-5 accent-[var(--uh-brand)]" @checked(old('demo', setting('site.demo') === '1'))>
                <span class="flex flex-col gap-1">
                    <strong>Demo mode</strong>
                    <span class="text-sm text-muted">Visitors can browse and sign in. Public changes and transactions are read-only. Admins remain unrestricted.</span>
                </span>
            </label>

            <label class="settings-subpanel settings-mode-option gap-3">
                <input type="checkbox" name="maintenance" value="1" class="mt-1 h-5 w-5 accent-[var(--uh-brand)]" @checked(old('maintenance', $modeEnabled))>
                <span class="flex flex-col gap-1">
                    <strong>Maintenance mode</strong>
                    <span class="text-sm text-muted">Visitors see a 503 page. Admins can still manage settings; payment confirmations continue to process.</span>
                </span>
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div><label for="demo_message" class="label">Demo banner message</label><input id="demo_message" name="demo_message" maxlength="240" value="{{ old('demo_message', setting('site.demo_message')) }}" class="input h-11" placeholder="Demo mode is on — actions that change data are disabled."><x-field-error name="demo_message" /></div>
            <div><label for="maintenance_message" class="label">Maintenance page message</label><input id="maintenance_message" name="maintenance_message" maxlength="300" value="{{ old('maintenance_message', setting('site.maintenance_message')) }}" class="input h-11" placeholder="We are making improvements. Please check back soon."><x-field-error name="maintenance_message" /></div>
            <div><label for="maintenance_starts_at" class="label">Maintenance starts ({{ $modeTimezone }})</label><input id="maintenance_starts_at" name="maintenance_starts_at" type="datetime-local" value="{{ old('maintenance_starts_at', $startInput) }}" class="input h-11"><p class="mt-1 text-xs text-muted">Leave empty to start as soon as you save.</p><x-field-error name="maintenance_starts_at" /></div>
            <div><label for="maintenance_ends_at" class="label">Maintenance ends ({{ $modeTimezone }})</label><input id="maintenance_ends_at" name="maintenance_ends_at" type="datetime-local" value="{{ old('maintenance_ends_at', $endInput) }}" class="input h-11"><p class="mt-1 text-xs text-muted">Leave empty to keep it on until an admin turns it off.</p><x-field-error name="maintenance_ends_at" /></div>
        </div>

        <p class="m-0 text-xs text-muted">Scheduled dates use {{ $modeTimezone }}. Maintenance mode takes priority over demo mode; a future start or expired end leaves the public site available.</p>
        <button class="btn btn-primary self-start">Save site mode</button>
    </form>
</section>
