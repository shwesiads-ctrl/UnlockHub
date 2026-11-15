<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site.name' => 'UnlockHub',
            'site.support_hours' => '9:00 AM – 10:00 PM',

            // Points: earn 1 point per 1,000 Ks spent (unless the service sets its own),
            // redeem 1 point = 10 Ks, minimum 100 points.
            'points.per_ks' => '1000',
            'points.redeem_rate' => '10',
            'points.redeem_min' => '100',
            'points.referral_bonus' => '50',

            // Display only — wallet is MMK
            'currency.usd_rate' => '4500',

            'social.telegram' => '',
            'social.viber' => '',
            'social.facebook' => '',
            'social.youtube' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
