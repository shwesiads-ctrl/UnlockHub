<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CountryPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function method(string $code, ?string $country, string $currency, float $rate, float $min = 1): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => strtoupper($code), 'code' => $code, 'country' => $country, 'currency' => $currency,
            'rate' => $rate, 'account_number' => '0812345678', 'min_amount' => $min, 'is_active' => true,
        ]);
    }

    private function owner(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin', 'staff_role' => 'owner'])->save();

        return $u->fresh();
    }

    public function test_language_picks_the_country_and_tab_switch_is_saved(): void
    {
        $this->method('kbzpay', 'MM', 'MMK', 1, 5000);
        $this->method('promptpay', 'TH', 'THB', 60, 100);
        $user = $this->customer(0);

        // Thai language → Thai methods only
        $this->actingAs($user)->withSession(['locale' => 'th'])->get(route('account.funds.index'))
            ->assertOk()->assertSee('PROMPTPAY')->assertDontSee('KBZPAY');

        // Choosing Myanmar is remembered on the account
        $this->actingAs($user)->get(route('account.funds.index', ['country' => 'MM']))
            ->assertOk()->assertSee('KBZPAY');
        $this->assertSame('MM', $user->fresh()->country);
    }

    public function test_foreign_topup_keeps_currency_and_rate_and_admin_credits_amount_times_rate(): void
    {
        Storage::fake(config('unlockhub.receipts.disk'));
        $method = $this->method('promptpay', 'TH', 'THB', 60, 100);
        $user = $this->customer(0);

        $this->actingAs($user)->post(route('account.funds.store'), [
            'method' => 'promptpay', 'amount' => 500, 'transaction_id' => 'TH123456',
            'receipt' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertRedirect(route('account.funds.index'));

        $pr = PaymentRequest::firstOrFail();
        $this->assertSame('THB', $pr->currency);
        $this->assertEquals(60, (float) $pr->rate);
        $this->assertEquals(30000, $pr->suggestedCredit());

        // Changing the method's rate later does not change this request
        $method->update(['rate' => 70]);
        $this->assertEquals(30000, $pr->fresh()->suggestedCredit());

        $admin = $this->owner();
        $this->actingAs($admin)->get(route('admin.payments.show', $pr))
            ->assertOk()->assertSee('500 THB')->assertSee('value="30000"', false);

        // Admin confirms 480 THB received at rate 61
        $this->actingAs($admin)->post(route('admin.payments.approve', $pr), [
            'paid_amount' => 480, 'rate' => 61, 'credited_amount' => 29280,
        ])->assertRedirect();

        $pr->refresh();
        $this->assertSame('approved', $pr->status);
        $this->assertEquals(480, (float) $pr->amount);
        $this->assertEquals(61, (float) $pr->rate);
        $this->assertEquals(29280, (float) $user->fresh()->balance);
    }

    public function test_foreign_method_cannot_be_turned_on_without_a_rate(): void
    {
        $m = PaymentMethod::create(['name' => 'Alipay', 'code' => 'alipay', 'country' => 'CN', 'currency' => 'CNY', 'rate' => 1, 'min_amount' => 20, 'is_active' => false]);

        $this->actingAs($this->owner())->put(route('admin.payment-methods.update', $m), [
            'name' => 'Alipay', 'country' => 'CN', 'currency' => 'CNY', 'rate' => 1,
            'account_number' => 'pay@example.com', 'min_amount' => 20, 'is_active' => 1,
        ])->assertSessionHasErrors('rate_'.$m->id);
        $this->assertFalse($m->fresh()->is_active);

        $this->actingAs($this->owner())->put(route('admin.payment-methods.update', $m), [
            'name' => 'Alipay', 'country' => 'CN', 'currency' => 'CNY', 'rate' => 590,
            'account_number' => 'pay@example.com', 'min_amount' => 20, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($m->fresh()->is_active);
    }

    public function test_home_shows_methods_for_the_visitor_country(): void
    {
        $this->method('kbzpay', 'MM', 'MMK', 1);
        $this->method('momo', 'VN', 'VND', 0.09);

        $this->get('/')->assertOk()->assertSee('KBZPAY')->assertDontSee('MOMO');
        $this->get('/?country=VN')->assertOk()->assertSee('MOMO')->assertDontSee('KBZPAY');
    }

    public function test_every_language_file_is_valid_and_complete(): void
    {
        $my = json_decode(file_get_contents(lang_path('my.json')), true);
        foreach (['th', 'zh', 'lo', 'vi'] as $locale) {
            $t = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
            $this->assertIsArray($t, "{$locale}.json is not valid JSON");
            $this->assertSame([], array_values(array_diff(array_keys($my), array_keys($t))), "{$locale}.json is missing keys");
        }

        $this->get('/locale/th')->assertRedirect();
        $this->withSession(['locale' => 'th'])->get('/')->assertOk()->assertSee('lang="th"', false)->assertSee('ไทย');
        $this->get('/locale/xx')->assertNotFound();
    }
}
