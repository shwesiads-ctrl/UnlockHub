<?php

namespace Tests\Feature;

use App\Models\OnlinePayment;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('payments.stripe.enabled', '1');
        Setting::put('payments.stripe.secret', encrypt('sk_test_abc'));
        Setting::put('payments.stripe.webhook_secret', encrypt('whsec_test'));
        Setting::put('payments.online.currency', 'USD');
        Setting::put('payments.online.rate', '4000');
        Setting::put('payments.online.min', '1');
        Setting::put('payments.online.max', '500');
        Setting::put('payments.online.fee_percent', '0');
    }

    private function fakeStripe(string $status = 'complete', string $payment = 'paid', int $amountTotal = 1000): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_1', 'status' => $status, 'payment_status' => $payment, 'amount_total' => $amountTotal, 'currency' => 'usd', 'payment_intent' => 'pi_1']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']),
        ]);
    }

    public function test_paid_once_even_if_confirmed_many_times(): void
    {
        $this->fakeStripe();
        $user = $this->customer(0);
        $svc = app(OnlinePaymentService::class);

        $url = $svc->start($user, 'stripe', 10);
        $this->assertStringStartsWith('https://checkout.stripe.com', $url);
        $p = OnlinePayment::firstOrFail();

        $svc->sync($p);
        $svc->sync($p->fresh());

        $this->assertSame('paid', $p->fresh()->status);
        $this->assertSame(1, WalletTransaction::where('type', 'deposit')->count());
        $this->assertEquals(40000, (float) $user->fresh()->balance);
    }

    public function test_wrong_amount_is_not_credited(): void
    {
        $this->fakeStripe(amountTotal: 100); // paid 1.00 instead of 10.00
        $user = $this->customer(0);
        $svc = app(OnlinePaymentService::class);
        $svc->start($user, 'stripe', 10);

        $svc->sync(OnlinePayment::firstOrFail());

        $this->assertSame('failed', OnlinePayment::first()->status);
        $this->assertEquals(0, (float) $user->fresh()->balance);
    }

    public function test_unpaid_session_stays_pending(): void
    {
        $this->fakeStripe('open', 'unpaid');
        $user = $this->customer(0);
        app(OnlinePaymentService::class)->start($user, 'stripe', 10);

        app(OnlinePaymentService::class)->sync(OnlinePayment::firstOrFail());

        $this->assertSame('pending', OnlinePayment::first()->status);
        $this->assertEquals(0, (float) $user->fresh()->balance);
    }

    public function test_webhook_signature_is_required(): void
    {
        $this->postJson('/api/payments/stripe/webhook', ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1']]])
            ->assertStatus(400);

        $payload = json_encode(['type' => 'x']);
        $t = time();
        $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test');
        $this->assertTrue(StripeGateway::validSignature($payload, $sig));
        $this->assertFalse(StripeGateway::validSignature($payload.'x', $sig));
    }
}
