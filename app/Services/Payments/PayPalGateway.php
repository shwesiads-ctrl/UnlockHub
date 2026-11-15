<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** PayPal Orders v2 over the REST API (no SDK). */
class PayPalGateway
{
    public static function enabled(): bool
    {
        return setting('payments.paypal.enabled') === '1' && setting('payments.paypal.client_id') && self::secret();
    }

    public static function live(): bool
    {
        return setting('payments.paypal.mode', 'sandbox') === 'live';
    }

    public static function secret(): ?string
    {
        try {
            return setting('payments.paypal.secret') ? decrypt(setting('payments.paypal.secret')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function base(): string
    {
        return self::live() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function token(): string
    {
        $key = 'paypal.token.'.md5(setting('payments.paypal.client_id').self::live());

        return Cache::remember($key, now()->addMinutes(25), function () {
            $res = Http::asForm()->timeout(20)
                ->withBasicAuth((string) setting('payments.paypal.client_id'), (string) self::secret())
                ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            if ($res->failed() || ! $res->json('access_token')) {
                throw new RuntimeException('PayPal: '.($res->json('error_description') ?? 'could not sign in (check Client ID / Secret and Sandbox / Live).'));
            }

            return $res->json('access_token');
        });
    }

    private function http()
    {
        return Http::withToken($this->token())->timeout(25)->acceptJson()->asJson();
    }

    /** @return array{id: string, url: string} */
    public function createOrder(OnlinePayment $p, string $returnUrl, string $cancelUrl): array
    {
        $res = $this->http()->withHeaders(['PayPal-Request-Id' => 'op-'.$p->id])->post($this->base().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $p->id,
                'custom_id' => (string) $p->id,
                'description' => 'Wallet top-up · '.number_format((float) $p->credit_ks).' Ks',
                'amount' => ['currency_code' => $p->currency, 'value' => number_format((float) $p->amount, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => mb_substr((string) setting('site.name', config('app.name')), 0, 127),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ]]],
        ]);
        $this->fail($res);

        $url = collect($res->json('links'))->firstWhere('rel', 'payer-action')['href']
            ?? collect($res->json('links'))->firstWhere('rel', 'approve')['href'] ?? null;
        if (! $url) {
            throw new RuntimeException('PayPal did not return an approval link.');
        }

        return ['id' => $res->json('id'), 'url' => $url];
    }

    /**
     * Capture if approved; read the result if already captured.
     *
     * @return array{paid: bool, amount: float, currency: string, txn: ?string, status: string}
     */
    public function settle(string $orderId): array
    {
        $order = $this->http()->get($this->base().'/v2/checkout/orders/'.rawurlencode($orderId));
        $this->fail($order);

        if ($order->json('status') === 'APPROVED') {
            $cap = $this->http()->withHeaders(['PayPal-Request-Id' => 'cap-'.$orderId])
                ->withBody('{}', 'application/json')->post($this->base().'/v2/checkout/orders/'.rawurlencode($orderId).'/capture');
            if ($cap->failed() && ! str_contains((string) $cap->body(), 'ORDER_ALREADY_CAPTURED')) {
                $this->fail($cap);
            }
            $order = $this->http()->get($this->base().'/v2/checkout/orders/'.rawurlencode($orderId));
            $this->fail($order);
        }

        $capture = $order->json('purchase_units.0.payments.captures.0') ?? [];

        return [
            'paid' => $order->json('status') === 'COMPLETED' && ($capture['status'] ?? null) === 'COMPLETED',
            'amount' => (float) ($capture['amount']['value'] ?? 0),
            'currency' => (string) ($capture['amount']['currency_code'] ?? ''),
            'txn' => $capture['id'] ?? null,
            'status' => (string) $order->json('status'),
        ];
    }

    public function ping(): string
    {
        $this->token();

        return (self::live() ? 'LIVE' : 'SANDBOX').' · credentials accepted';
    }

    private function fail($res): void
    {
        if ($res->failed()) {
            throw new RuntimeException('PayPal: '.($res->json('details.0.description') ?? $res->json('message') ?? 'HTTP '.$res->status()));
        }
    }
}
