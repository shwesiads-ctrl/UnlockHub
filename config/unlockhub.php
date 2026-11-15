<?php

return [
    'version' => '1.0.0',


    // Run queued jobs from the scheduler cron (shared hosting without a worker)
    'queue_in_scheduler' => (bool) env('QUEUE_IN_SCHEDULER', false),

    // Telegram bot for admin alerts (new receipts, new manual orders, API failures)
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
    ],

    // Receipt uploads
    'receipts' => [
        'disk' => 'local',              // private: storage/app/private
        'max_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    // Supplier API polling
    'api' => [
        'max_attempts' => 3,            // place-order retries before falling back to manual
        'check_every_minutes' => 5,     // poll In Process API orders
        'timeout_seconds' => 30,
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'trusted_device_days' => 30,
    ],

    'locales' => [
        'en' => 'English',
        'my' => 'မြန်မာ',
        'th' => 'ไทย',
        'zh' => '中文',
        'lo' => 'ລາວ',
        'vi' => 'Tiếng Việt',
    ],

    // Visitor country (Cloudflare / GeoIP / browser) → first language shown.
    'country_locale' => [
        'MM' => 'my', 'TH' => 'th', 'LA' => 'lo', 'VN' => 'vi',
        'CN' => 'zh', 'HK' => 'zh', 'TW' => 'zh', 'MO' => 'zh', 'SG' => 'zh',
    ],

    // Language → country, used to pick payment methods for visitors without a saved country.
    'locale_country' => ['my' => 'MM', 'th' => 'TH', 'zh' => 'CN', 'lo' => 'LA', 'vi' => 'VN'],

    // Countries customers can choose (payment methods are grouped by these).
    // Quick-amount buttons on Add Funds, per currency of the payment method.
    'quick_amounts' => [
        'MMK' => [10000, 50000, 100000, 500000],
        'THB' => [100, 500, 1000, 5000],
        'CNY' => [20, 100, 500, 1000],
        'LAK' => [50000, 100000, 500000, 1000000],
        'VND' => [50000, 100000, 500000, 1000000],
    ],

    'countries' => [
        'MM' => 'Myanmar',
        'TH' => 'Thailand',
        'CN' => 'China',
        'LA' => 'Laos',
        'VN' => 'Vietnam',
    ],
    'currencies' => ['MMK', 'USD'],
];
