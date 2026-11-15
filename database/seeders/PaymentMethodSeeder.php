<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /** Account names/numbers are left empty — fill them in Admin › Settings › Payment methods. */
    public function run(): void
    {
        $note = 'Write your username in the transfer note, then upload the receipt screenshot.';

        // Myanmar — amounts in Ks.
        $methods = [
            ['name' => 'KBZPay', 'code' => 'kbzpay', 'country' => 'MM', 'currency' => 'MMK', 'min_amount' => 5000, 'sort' => 1],
            ['name' => 'WavePay', 'code' => 'wavepay', 'country' => 'MM', 'currency' => 'MMK', 'min_amount' => 5000, 'sort' => 2],
            ['name' => 'AYA Pay', 'code' => 'ayapay', 'country' => 'MM', 'currency' => 'MMK', 'min_amount' => 5000, 'sort' => 3],
            ['name' => 'CB Pay', 'code' => 'cbpay', 'country' => 'MM', 'currency' => 'MMK', 'min_amount' => 5000, 'sort' => 4],

            // Other countries — amounts in their own currency. The rate (Ks per 1 unit)
            // must be set in Admin before a method can be turned on.
            ['name' => 'PromptPay', 'code' => 'promptpay', 'country' => 'TH', 'currency' => 'THB', 'min_amount' => 100, 'sort' => 10],
            ['name' => 'TrueMoney Wallet', 'code' => 'truemoney', 'country' => 'TH', 'currency' => 'THB', 'min_amount' => 100, 'sort' => 11],
            ['name' => 'Alipay', 'code' => 'alipay', 'country' => 'CN', 'currency' => 'CNY', 'min_amount' => 20, 'sort' => 20],
            ['name' => 'WeChat Pay', 'code' => 'wechatpay', 'country' => 'CN', 'currency' => 'CNY', 'min_amount' => 20, 'sort' => 21],
            ['name' => 'BCEL One', 'code' => 'bcelone', 'country' => 'LA', 'currency' => 'LAK', 'min_amount' => 50000, 'sort' => 30],
            ['name' => 'U-Money', 'code' => 'umoney', 'country' => 'LA', 'currency' => 'LAK', 'min_amount' => 50000, 'sort' => 31],
            ['name' => 'MoMo', 'code' => 'momo', 'country' => 'VN', 'currency' => 'VND', 'min_amount' => 50000, 'sort' => 40],
            ['name' => 'VietQR (bank transfer)', 'code' => 'vietqr', 'country' => 'VN', 'currency' => 'VND', 'min_amount' => 50000, 'sort' => 41],
        ];

        foreach ($methods as $m) {
            $method = PaymentMethod::firstOrCreate(['code' => $m['code']], $m + [
                'instructions' => $note,
                'rate' => 1,
                'is_active' => false, // activate after adding the real account number (and rate)
            ]);

            // Older installs: the Myanmar methods existed before countries did.
            if ($method->country === null && $m['country'] === 'MM') {
                $method->forceFill(['country' => 'MM', 'currency' => 'MMK'])->save();
            }
        }
    }
}
