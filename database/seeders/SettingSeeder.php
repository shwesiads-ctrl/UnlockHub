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
            'home.reseller_cta_enabled' => '1',
            'home.reseller_cta_label' => 'Become a reseller',
            'home.hero_badge' => 'For repair shops & resellers in Myanmar',
            'home.hero_title' => 'Unlock codes, tool credits & activations — in one wallet.',
            'home.hero_description' => 'Top up with :methods, place IMEI and server orders, track every result, and earn points on each successful order.',
            'home.browse_label' => 'Browse services',
            'home.trust_1' => 'Auto refund on rejected orders',
            'home.trust_2' => 'Reseller group pricing',
            'home.trust_3' => '2FA secured accounts',
            'home.show_categories' => '1',
            'home.categories_title' => 'Service categories',
            'home.category_imei_title' => 'IMEI / Serial Services',
            'home.category_imei_description' => 'Carrier unlocks, NCK/MCK codes, IMEI & GSX checks. Submit IMEI, SN or ECID.',
            'home.category_server_title' => 'Server · Box · Tool Credits',
            'home.category_server_description' => 'UnlockTool, Chimera, TFM, dongle renewals, game credits and gift cards.',
            'home.category_remote_title' => 'Remote · Rent · Files',
            'home.category_remote_description' => 'Live remote sessions, hourly tool rental and paid firmware files.',
            'home.show_hot_services' => '1',
            'home.hot_title' => 'Hot services',
            'home.show_how_it_works' => '1',
            'home.how_title' => 'How it works',
            'home.step_1_title' => 'Register & verify',
            'home.step_1_description' => 'Email OTP and optional Google Authenticator.',
            'home.step_2_title' => 'Add funds',
            'home.step_2_description' => 'Transfer by mobile pay, upload the receipt, admin approves.',
            'home.step_3_title' => 'Place orders',
            'home.step_3_description' => 'Single or bulk IMEI. Credit is held until the result arrives.',
            'home.step_4_title' => 'Get result & points',
            'home.step_4_description' => 'Code delivered to your orders page; points added on success.',
            'home.show_payment_banner' => '1',
            'home.payment_title' => '',
            'home.payment_description' => 'Transfer, upload your receipt and your wallet is credited after approval.',

            // Points: earn 1 point per 1,000 Ks spent (unless the service sets its own),
            // redeem 1 point = 10 Ks, minimum 100 points.
            'points.per_ks' => '1000',
            'points.redeem_rate' => '10',
            'points.convert_rate' => '10',
            'catalog.price_currency' => 'ks',
            'reseller.auto_verify_enabled' => '0',
            'reseller.auto_verify_amount' => '1000000',
            'points.redeem_min' => '100',
            'points.referral_bonus' => '50',
            'points.signup_bonus' => '20',
            'points.profile_bonus' => '30',
            'points.topup_per_ks' => '0',
            'points.checkin_points' => '1',
            'points.checkin_streak_bonus' => '1',
            'points.checkin_streak_max' => '7',

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
