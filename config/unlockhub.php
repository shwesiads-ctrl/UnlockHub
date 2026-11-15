<?php

return [

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

    'locales' => ['en' => 'English', 'my' => 'မြန်မာ'],
    'currencies' => ['MMK', 'USD'],
];
