<?php

namespace App\Support;

final class ProfileSocialLinks
{
    public const PROVIDERS = [
        'google' => ['label' => 'Google account', 'placeholder' => 'name@gmail.com'],
        'facebook' => ['label' => 'Facebook', 'placeholder' => 'Profile URL or username'],
        'discord' => ['label' => 'Discord', 'placeholder' => 'Username'],
        'tiktok' => ['label' => 'TikTok', 'placeholder' => '@username or profile URL'],
        'telegram' => ['label' => 'Telegram', 'placeholder' => '@username or t.me link'],
    ];

    /** Return a safe profile/contact URL for a saved address or handle. */
    public static function url(string $provider, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($provider === 'google' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:'.$value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);
            $host = strtolower($parts['host'] ?? '');
            $allowed = match ($provider) {
                'google' => ['google.com', 'accounts.google.com', 'myaccount.google.com'],
                'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
                'discord' => ['discord.com', 'discordapp.com'],
                'tiktok' => ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com'],
                'telegram' => ['t.me', 'telegram.me'],
                default => [],
            };

            if (in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) && in_array($host, $allowed, true)) {
                return $value;
            }

            return null;
        }

        $handle = ltrim($value, '@');
        return match ($provider) {
            'facebook' => preg_match('/^[A-Za-z0-9.]{3,50}$/D', $handle) ? 'https://www.facebook.com/'.$handle : null,
            'tiktok' => preg_match('/^[A-Za-z0-9._]{2,24}$/D', $handle) ? 'https://www.tiktok.com/@'.$handle : null,
            'telegram' => preg_match('/^[A-Za-z0-9_]{5,32}$/D', $handle) ? 'https://t.me/'.$handle : null,
            default => null,
        };
    }
}
