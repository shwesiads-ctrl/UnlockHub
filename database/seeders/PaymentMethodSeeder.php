<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /** Account names/numbers are left empty — fill them in Admin › Settings › Payment methods. */
    public function run(): void
    {
        $methods = [
            ['name' => 'KBZPay', 'code' => 'kbzpay', 'sort' => 1],
            ['name' => 'WavePay', 'code' => 'wavepay', 'sort' => 2],
            ['name' => 'AYA Pay', 'code' => 'ayapay', 'sort' => 3],
            ['name' => 'CB Pay', 'code' => 'cbpay', 'sort' => 4],
        ];

        foreach ($methods as $m) {
            PaymentMethod::firstOrCreate(['code' => $m['code']], $m + [
                'instructions' => 'Write your username in the transfer note, then upload the receipt screenshot.',
                'min_amount' => 5000,
                'is_active' => false, // activate after adding the real account number
            ]);
        }
    }
}
