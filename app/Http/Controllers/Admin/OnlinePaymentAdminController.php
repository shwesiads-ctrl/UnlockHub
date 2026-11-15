<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnlinePayment;
use App\Models\Setting;
use App\Models\ActivityLog;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnlinePaymentAdminController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.payments.online', [
            'payments' => OnlinePayment::with('user:id,username')
                ->when(in_array($request->query('status'), ['pending', 'paid', 'failed', 'cancelled', 'expired'], true), fn ($q) => $q->where('status', $request->query('status')))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function sync(OnlinePayment $payment, OnlinePaymentService $payments): RedirectResponse
    {
        $p = $payments->sync($payment);

        return back()->with('success', "Payment #{$p->id}: {$p->status}.");
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'regex:/^[A-Za-z]{3}$/'],
            'rate' => ['required', 'numeric', 'min:0.0001'],
            'min' => ['required', 'numeric', 'min:0.5'],
            'max' => ['required', 'numeric', 'gte:min'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:20'],
            'stripe_secret' => ['nullable', 'string', 'regex:/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/'],
            'stripe_webhook_secret' => ['nullable', 'string', 'regex:/^whsec_[A-Za-z0-9]+$/'],
            'paypal_client_id' => ['nullable', 'string', 'max:200'],
            'paypal_secret' => ['nullable', 'string', 'max:200'],
            'paypal_mode' => ['required', 'in:sandbox,live'],
        ], ['stripe_secret.regex' => 'Stripe secret key starts with sk_test_ or sk_live_.', 'stripe_webhook_secret.regex' => 'Webhook signing secret starts with whsec_.']);

        foreach (['currency', 'rate', 'min', 'max', 'fee_percent'] as $k) {
            Setting::put('payments.online.'.$k, $k === 'currency' ? strtoupper($data[$k]) : (string) $data[$k]);
        }
        Setting::put('payments.stripe.enabled', $request->boolean('stripe_enabled') ? '1' : '0');
        Setting::put('payments.paypal.enabled', $request->boolean('paypal_enabled') ? '1' : '0');
        Setting::put('payments.paypal.mode', $data['paypal_mode']);
        if (($data['paypal_client_id'] ?? null) !== setting('payments.paypal.client_id')) {
            Setting::put('payments.paypal.client_id', $data['paypal_client_id'] ?? null);
            Setting::put('payments.paypal.verified_at', null);
        }
        foreach (['stripe_secret' => 'payments.stripe.secret', 'stripe_webhook_secret' => 'payments.stripe.webhook_secret', 'paypal_secret' => 'payments.paypal.secret'] as $field => $key) {
            if (filled($data[$field] ?? null)) {
                Setting::put($key, encrypt($data[$field]));
                Setting::put(str_starts_with($key, 'payments.stripe') ? 'payments.stripe.verified_at' : 'payments.paypal.verified_at', null);
            }
        }
        ActivityLog::record('settings.online_payments', null, ['stripe' => $request->boolean('stripe_enabled'), 'paypal' => $request->boolean('paypal_enabled')]);

        return redirect()->to(route('admin.settings.index').'#online-payments')->with('success', 'Payment gateway settings saved. Use Test to confirm the keys.');
    }

    public function test(string $gateway): RedirectResponse
    {
        $back = redirect()->to(route('admin.settings.index').'#online-payments');
        try {
            $msg = $gateway === 'stripe' ? app(StripeGateway::class)->ping() : app(PayPalGateway::class)->ping();
        } catch (\Throwable $e) {
            Setting::put("payments.{$gateway}.verified_at", null);

            return $back->with('error', ucfirst($gateway).' test failed: '.\Illuminate\Support\Str::limit($e->getMessage(), 250));
        }
        Setting::put("payments.{$gateway}.verified_at", now()->toDateTimeString());

        return $back->with('success', ucfirst($gateway)." keys work ✓ ({$msg}).");
    }
}
