<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Stripe Checkout over the REST API (no SDK). */
class StripeGateway
{
    private const API = 'https://api.stripe.com/v1/';

    /** Currencies Stripe charges in whole units. */
    private const ZERO_DECIMAL = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    public static function enabled(): bool
    {
        return setting('payments.stripe.enabled') === '1' && self::secret() !== null;
    }

    public static function secret(): ?string
    {
        try {
            return setting('payments.stripe.secret') ? decrypt(setting('payments.stripe.secret')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function webhookSecret(): ?string
    {
        try {
            return setting('payments.stripe.webhook_secret') ? decrypt(setting('payments.stripe.webhook_secret')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function testMode(): bool
    {
        return str_starts_with((string) self::secret(), 'sk_test_') || str_starts_with((string) self::secret(), 'rk_test_');
    }

    public static function minor(float $amount, string $currency): int
    {
        return in_array(strtolower($currency), self::ZERO_DECIMAL, true) ? (int) round($amount) : (int) round($amount * 100);
    }

    /** @return array{id: string, url: string} */
    public function createCheckout(OnlinePayment $p, string $successUrl, string $cancelUrl, ?string $email): array
    {
        $res = $this->http()->asForm()->post(self::API.'checkout/sessions', array_filter([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $p->id,
            'customer_email' => $email,
            'metadata[online_payment_id]' => (string) $p->id,
            'payment_intent_data[metadata][online_payment_id]' => (string) $p->id,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($p->currency),
            'line_items[0][price_data][unit_amount]' => self::minor((float) $p->amount, $p->currency),
            'line_items[0][price_data][product_data][name]' => 'Wallet top-up · '.number_format((float) $p->credit_ks).' Ks',
            'expires_at' => now()->addMinutes(60)->timestamp,
        ], fn ($v) => $v !== null && $v !== ''));

        $this->fail($res);

        return ['id' => $res->json('id'), 'url' => $res->json('url')];
    }

    /** @return array{paid: bool, amount: float, currency: string, txn: ?string, expired: bool} */
    public function status(string $sessionId): array
    {
        $res = $this->http()->get(self::API.'checkout/sessions/'.rawurlencode($sessionId));
        $this->fail($res);
        $currency = (string) $res->json('currency');
        $total = (int) $res->json('amount_total');

        return [
            'paid' => $res->json('payment_status') === 'paid' && $res->json('status') === 'complete',
            'amount' => in_array($currency, self::ZERO_DECIMAL, true) ? $total : $total / 100,
            'currency' => strtoupper($currency),
            'txn' => is_string($res->json('payment_intent')) ? $res->json('payment_intent') : null,
            'expired' => $res->json('status') === 'expired',
        ];
    }

    public function ping(): string
    {
        $res = $this->http()->get(self::API.'balance');
        $this->fail($res);

        return (self::testMode() ? 'TEST mode' : 'LIVE mode').' · available: '.collect($res->json('available'))->map(fn ($b) => number_format($b['amount'] / 100, 2).' '.strtoupper($b['currency']))->implode(', ');
    }

    /** Stripe-Signature: t=…,v1=… ; HMAC-SHA256 of "t.payload", 5-minute tolerance. */
    public static function validSignature(string $payload, string $header): bool
    {
        $secret = self::webhookSecret();
        if (! $secret || $header === '') {
            return false;
        }
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int) $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if (! $t || abs(time() - $t) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $t.'.'.$payload, $secret);
        foreach ($sigs as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }

        return false;
    }

    private function http()
    {
        $key = self::secret() ?? throw new RuntimeException('Stripe secret key is not set.');

        return Http::withToken($key)->timeout(20)->acceptJson();
    }

    private function fail($res): void
    {
        if ($res->failed()) {
            throw new RuntimeException('Stripe: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }
    }
}
