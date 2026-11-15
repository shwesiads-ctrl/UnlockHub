{{-- Card (Stripe) and PayPal top-ups --}}
@php
    $oc = \App\Services\Payments\OnlinePaymentService::config();
    $badge = fn ($g) => setting("payments.{$g}.verified_at")
        ? '<span class="badge badge-success">Tested ✓ '.e(\Illuminate\Support\Carbon::parse(setting("payments.{$g}.verified_at"))->diffForHumans()).'</span>'
        : '<span class="badge badge-pending">Not tested</span>';
@endphp
<section id="online-payments" class="settings-page-panel card p-6 flex flex-col gap-5">
    <div class="settings-heading">
        <span class="badge badge-info">Payments</span>
        <h2>PayPal &amp; Stripe (automatic top-up)</h2>
        <p>Customers pay by card or PayPal and the wallet is credited automatically — only after the gateway confirms the money arrived (checked with its API, never from the browser).</p>
    </div>
    <form method="POST" action="{{ route('admin.settings.payments') }}" class="flex flex-col gap-4" autocomplete="off">
        @csrf @method('PUT')
        <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(150px,1fr))]">
            <div><label for="op_cur" class="label text-xs">Charge currency</label><input id="op_cur" name="currency" value="{{ $oc['currency'] }}" maxlength="3" class="input h-10 uppercase"></div>
            <div><label for="op_rate" class="label text-xs">1 {{ $oc['currency'] }} = ? Ks credited</label><input id="op_rate" name="rate" type="number" step="0.0001" min="0" value="{{ $oc['rate'] }}" class="input h-10"></div>
            <div><label for="op_min" class="label text-xs">Min ({{ $oc['currency'] }})</label><input id="op_min" name="min" type="number" step="0.01" value="{{ $oc['min'] }}" class="input h-10"></div>
            <div><label for="op_max" class="label text-xs">Max ({{ $oc['currency'] }})</label><input id="op_max" name="max" type="number" step="0.01" value="{{ $oc['max'] }}" class="input h-10"></div>
            <div><label for="op_fee" class="label text-xs">Fee added (%)</label><input id="op_fee" name="fee_percent" type="number" step="0.1" min="0" max="20" value="{{ $oc['fee_percent'] }}" class="input h-10"></div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <fieldset class="m-0 rounded-xl border border-line p-4 flex flex-col gap-3">
                <legend class="px-1 font-bold">Stripe (cards)</legend>
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="stripe_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('payments.stripe.enabled') === '1')>Enabled</label>
                <div><label for="st_sk" class="label text-xs">Secret key (sk_test_… / sk_live_…)</label><input id="st_sk" name="stripe_secret" type="password" class="input h-10 font-mono text-sm" autocomplete="new-password" placeholder="{{ setting('payments.stripe.secret') ? 'Saved — leave empty to keep' : 'sk_live_…' }}"><x-field-error name="stripe_secret" /></div>
                <div><label for="st_wh" class="label text-xs">Webhook signing secret (whsec_…)</label><input id="st_wh" name="stripe_webhook_secret" type="password" class="input h-10 font-mono text-sm" autocomplete="new-password" placeholder="{{ setting('payments.stripe.webhook_secret') ? 'Saved — leave empty to keep' : 'whsec_…' }}"><x-field-error name="stripe_webhook_secret" /></div>
                <div class="text-xs text-muted">Webhook URL (Developers › Webhooks › Add endpoint, events <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.expired</code>):<br><code class="text-ink break-all select-all">{{ route('webhooks.stripe') }}</code> <button type="button" class="btn h-7 px-2 text-[11px]" data-copy="{{ route('webhooks.stripe') }}">Copy</button></div>
                <div class="flex items-center gap-2">{!! $badge('stripe') !!} @if (\App\Services\Payments\StripeGateway::secret()) <span class="text-xs text-muted">{{ \App\Services\Payments\StripeGateway::testMode() ? 'Test keys' : 'Live keys' }}</span> @endif
                    <button form="test-stripe" class="btn h-8 text-xs ml-auto" @disabled(! setting('payments.stripe.secret'))>Test keys</button></div>
            </fieldset>

            <fieldset class="m-0 rounded-xl border border-line p-4 flex flex-col gap-3">
                <legend class="px-1 font-bold">PayPal</legend>
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="paypal_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('payments.paypal.enabled') === '1')>Enabled</label>
                <div><label for="pp_mode" class="label text-xs">Mode</label><select id="pp_mode" name="paypal_mode" class="input h-10"><option value="sandbox" @selected(setting('payments.paypal.mode', 'sandbox') === 'sandbox')>Sandbox (testing)</option><option value="live" @selected(setting('payments.paypal.mode') === 'live')>Live</option></select></div>
                <div><label for="pp_id" class="label text-xs">Client ID</label><input id="pp_id" name="paypal_client_id" value="{{ setting('payments.paypal.client_id') }}" class="input h-10 font-mono text-sm"></div>
                <div><label for="pp_sec" class="label text-xs">Secret</label><input id="pp_sec" name="paypal_secret" type="password" class="input h-10 font-mono text-sm" autocomplete="new-password" placeholder="{{ setting('payments.paypal.secret') ? 'Saved — leave empty to keep' : '' }}"></div>
                <div class="text-xs text-muted">developer.paypal.com › Apps &amp; Credentials. Optional webhook (events <code>CHECKOUT.ORDER.APPROVED</code>, <code>PAYMENT.CAPTURE.COMPLETED</code>):<br><code class="text-ink break-all select-all">{{ route('webhooks.paypal') }}</code> <button type="button" class="btn h-7 px-2 text-[11px]" data-copy="{{ route('webhooks.paypal') }}">Copy</button></div>
                <div class="flex items-center gap-2">{!! $badge('paypal') !!}<button form="test-paypal" class="btn h-8 text-xs ml-auto" @disabled(! setting('payments.paypal.secret'))>Test keys</button></div>
            </fieldset>
        </div>
        <p class="m-0 text-xs text-muted">Testing: Stripe test card <code>4242 4242 4242 4242</code>, any future date / CVC. PayPal: a Sandbox buyer account from the developer dashboard. Payments are also re-checked every 5 minutes (cron) in case a webhook is missed.</p>
        <div class="settings-actions"><button class="btn btn-primary">Save payment gateways</button></div>
    </form>
    <form id="test-stripe" method="POST" action="{{ route('admin.settings.payments.test', 'stripe') }}">@csrf</form>
    <form id="test-paypal" method="POST" action="{{ route('admin.settings.payments.test', 'paypal') }}">@csrf</form>
</section>
