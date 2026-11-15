<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * OAuth 2.0 login with Google, Facebook, Discord and TikTok using plain HTTP
 * (no extra packages). Telegram uses its Login Widget (see verifyTelegram).
 *
 * Credentials live in settings: social.{provider}.enabled / client_id / client_secret (encrypted).
 */
class SocialProviders
{
    public const PROVIDERS = [
        'google' => ['label' => 'Google', 'console' => 'https://console.cloud.google.com/apis/credentials'],
        'facebook' => ['label' => 'Facebook', 'console' => 'https://developers.facebook.com/apps'],
        'discord' => ['label' => 'Discord', 'console' => 'https://discord.com/developers/applications'],
        'tiktok' => ['label' => 'TikTok', 'console' => 'https://developers.tiktok.com/apps'],
        'telegram' => ['label' => 'Telegram', 'console' => 'https://t.me/BotFather'],
    ];

    public static function enabled(): array
    {
        return array_keys(array_filter(self::PROVIDERS, fn ($p, $key) => self::isEnabled($key), ARRAY_FILTER_USE_BOTH));
    }

    public static function isEnabled(string $provider): bool
    {
        if (! isset(self::PROVIDERS[$provider]) || setting("social_login.{$provider}.enabled") !== '1') {
            return false;
        }

        return $provider === 'telegram'
            ? filled(self::telegramBotUsername()) && filled(self::telegramBotToken())
            : filled(self::clientId($provider)) && filled(self::clientSecret($provider));
    }

    public static function clientId(string $provider): ?string
    {
        return setting("social_login.{$provider}.client_id") ?: null;
    }

    public static function clientSecret(string $provider): ?string
    {
        $v = setting("social_login.{$provider}.client_secret");
        try {
            return $v ? decrypt($v) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function telegramBotUsername(): ?string
    {
        return ltrim((string) setting('social_login.telegram.bot_username'), '@') ?: null;
    }

    public static function telegramBotToken(): ?string
    {
        $v = setting('social_login.telegram.bot_token');
        try {
            return $v ? decrypt($v) : \App\Support\Notify::telegramToken();
        } catch (Throwable) {
            return null;
        }
    }

    public static function redirectUri(string $provider): string
    {
        return route('social.callback', $provider);
    }

    /** URL to send the user to. */
    public function authorizeUrl(string $provider, string $state): string
    {
        $id = self::clientId($provider);
        $redirect = self::redirectUri($provider);

        return match ($provider) {
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $id, 'redirect_uri' => $redirect, 'response_type' => 'code',
                'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account',
            ]),
            'facebook' => 'https://www.facebook.com/v19.0/dialog/oauth?'.http_build_query([
                'client_id' => $id, 'redirect_uri' => $redirect, 'response_type' => 'code',
                'scope' => 'email,public_profile', 'state' => $state,
            ]),
            'discord' => 'https://discord.com/oauth2/authorize?'.http_build_query([
                'client_id' => $id, 'redirect_uri' => $redirect, 'response_type' => 'code',
                'scope' => 'identify email', 'state' => $state, 'prompt' => 'none',
            ]),
            'tiktok' => 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
                'client_key' => $id, 'redirect_uri' => $redirect, 'response_type' => 'code',
                'scope' => 'user.info.basic', 'state' => $state,
            ]),
            default => throw new RuntimeException('Unknown provider.'),
        };
    }

    /**
     * Exchange the code and fetch the profile.
     *
     * @return array{id: string, name: ?string, email: ?string, email_verified: bool, avatar: ?string}
     */
    public function user(string $provider, string $code): array
    {
        $id = self::clientId($provider);
        $secret = self::clientSecret($provider);
        $redirect = self::redirectUri($provider);
        $http = Http::timeout(15)->acceptJson();

        switch ($provider) {
            case 'google':
                $token = $http->asForm()->post('https://oauth2.googleapis.com/token', [
                    'code' => $code, 'client_id' => $id, 'client_secret' => $secret,
                    'redirect_uri' => $redirect, 'grant_type' => 'authorization_code',
                ])->throw()->json('access_token');
                $u = $http->withToken($token)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();

                return [
                    'id' => (string) $u['sub'], 'name' => $u['name'] ?? null, 'email' => $u['email'] ?? null,
                    'email_verified' => (bool) ($u['email_verified'] ?? false), 'avatar' => $u['picture'] ?? null,
                ];

            case 'facebook':
                $token = $http->get('https://graph.facebook.com/v19.0/oauth/access_token', [
                    'client_id' => $id, 'client_secret' => $secret, 'redirect_uri' => $redirect, 'code' => $code,
                ])->throw()->json('access_token');
                $u = $http->get('https://graph.facebook.com/v19.0/me', [
                    'fields' => 'id,name,email,picture.type(large)', 'access_token' => $token,
                ])->throw()->json();

                return [
                    'id' => (string) $u['id'], 'name' => $u['name'] ?? null, 'email' => $u['email'] ?? null,
                    // Facebook only returns confirmed emails
                    'email_verified' => isset($u['email']), 'avatar' => $u['picture']['data']['url'] ?? null,
                ];

            case 'discord':
                $token = $http->asForm()->post('https://discord.com/api/oauth2/token', [
                    'code' => $code, 'client_id' => $id, 'client_secret' => $secret,
                    'redirect_uri' => $redirect, 'grant_type' => 'authorization_code',
                ])->throw()->json('access_token');
                $u = $http->withToken($token)->get('https://discord.com/api/users/@me')->throw()->json();

                return [
                    'id' => (string) $u['id'], 'name' => $u['global_name'] ?? $u['username'] ?? null,
                    'email' => $u['email'] ?? null, 'email_verified' => (bool) ($u['verified'] ?? false),
                    'avatar' => isset($u['avatar']) ? "https://cdn.discordapp.com/avatars/{$u['id']}/{$u['avatar']}.png" : null,
                ];

            case 'tiktok':
                $token = $http->asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
                    'client_key' => $id, 'client_secret' => $secret, 'code' => $code,
                    'grant_type' => 'authorization_code', 'redirect_uri' => $redirect,
                ])->throw()->json('access_token');
                $u = $http->withToken($token)
                    ->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'open_id,union_id,avatar_url,display_name'])
                    ->throw()->json('data.user');

                return [
                    'id' => (string) ($u['union_id'] ?? $u['open_id']), 'name' => $u['display_name'] ?? null,
                    'email' => null, 'email_verified' => false, 'avatar' => $u['avatar_url'] ?? null,
                ];
        }

        throw new RuntimeException('Unknown provider.');
    }

    /**
     * Telegram Login Widget: check the hash Telegram signed with our bot token.
     * https://core.telegram.org/widgets/login#checking-authorization
     *
     * @return array{id: string, name: ?string, email: null, email_verified: false, avatar: ?string}
     */
    public function verifyTelegram(array $data): array
    {
        $token = self::telegramBotToken();
        $hash = (string) ($data['hash'] ?? '');

        if (! $token || $hash === '' || empty($data['id'])) {
            throw new RuntimeException('Telegram login data is missing.');
        }

        $fields = collect($data)->only(['auth_date', 'first_name', 'id', 'last_name', 'photo_url', 'username'])
            ->filter(fn ($v) => $v !== null && $v !== '')->sortKeys();
        $checkString = $fields->map(fn ($v, $k) => "{$k}={$v}")->implode("\n");
        $expected = hash_hmac('sha256', $checkString, hash('sha256', $token, true));

        if (! hash_equals($expected, $hash)) {
            throw new RuntimeException('Telegram login could not be verified.');
        }

        if (now()->timestamp - (int) $data['auth_date'] > 86400) {
            throw new RuntimeException('Telegram login expired. Please try again.');
        }

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($data['username'] ?? null);

        return [
            'id' => (string) $data['id'], 'name' => $name, 'email' => null,
            'email_verified' => false, 'avatar' => $data['photo_url'] ?? null,
            'username' => $data['username'] ?? null,
        ];
    }

    /** A free username based on the person's name. */
    public static function suggestUsername(?string ...$candidates): string
    {
        $base = '';
        foreach ($candidates as $c) {
            $base = Str::of((string) $c)->before('@')->ascii()->lower()->replaceMatches('/[^a-z0-9_]+/', '')->limit(20, '')->value();
            if (strlen($base) >= 3) {
                break;
            }
            $base = '';
        }
        $base = $base ?: 'user';
        $name = $base;
        while (\App\Models\User::where('username', $name)->exists()) {
            $name = $base.random_int(100, 9999);
        }

        return $name;
    }
}
