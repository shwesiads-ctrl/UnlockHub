<form method="POST" action="{{ route('admin.settings.analytics') }}" class="card p-6 flex flex-col gap-4">
    @csrf @method('PUT')
    <div class="settings-heading">
        <span class="badge badge-info">Privacy &amp; measurement</span>
        <h2>Google Analytics &amp; cookie consent</h2>
        <p>Analytics runs only on public pages after a visitor accepts optional cookies. Visitors can change their choice from the site footer.</p>
    </div>
    <div class="max-w-xl">
        <label for="seo_ga_id" class="label">Google Analytics 4 Measurement ID</label>
        <input id="seo_ga_id" name="google_analytics_id" value="{{ old('google_analytics_id', setting('seo.google_analytics_id')) }}" class="input h-11 font-mono" placeholder="G-XXXXXXXXXX" autocomplete="off">
        <x-field-error name="google_analytics_id" />
    </div>
    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="analytics_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('analytics_enabled', setting('seo.analytics_enabled', '0')) === '1')>Enable Google Analytics and show the cookie consent banner</label>
    <p class="m-0 text-xs text-muted">Enter the Measurement ID from Google Analytics → Admin → Data streams. Admin pages are excluded. When turned off, analytics stops loading and the consent banner is hidden. Include your privacy/cookie notice where local rules require it.</p>
    <div class="settings-actions"><button class="btn btn-primary">Save analytics and cookie settings</button></div>
</form>
