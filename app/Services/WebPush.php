<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Web Push without extra packages: VAPID (ES256 JWT) + an EMPTY push message.
 * The service worker then fetches the user's latest notification over HTTPS with their
 * session, so no personal data passes through the push service.
 */
class WebPush
{
    /** Push services browsers use (endpoint host must end with one of these: stops SSRF). */
    public const HOSTS = ['fcm.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'];

    public static function enabled(): bool
    {
        return setting('push.enabled', '0') === '1' && self::publicKey() !== null;
    }

    public static function publicKey(): ?string
    {
        return setting('push.vapid_public') ?: null;
    }

    public static function validEndpoint(string $url): bool
    {
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
            return false;
        }
        foreach (self::HOSTS as $h) {
            if ($p['host'] === $h || str_ends_with($p['host'], '.'.$h)) {
                return true;
            }
        }

        return false;
    }

    /** Create a new P-256 key pair (old subscriptions stop working). */
    public static function generateKeys(): string
    {
        // Windows PHP installs often have no openssl.cnf at OpenSSL's compiled-in path.
        // Provide a small valid config and use the EC-specific option supported by PHP 8.
        $configPath = tempnam(sys_get_temp_dir(), 'unlockhub-openssl-');
        if ($configPath === false || file_put_contents($configPath, "[req]\ndefault_bits = 256\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n") === false) {
            if ($configPath !== false) {
                @unlink($configPath);
            }
            throw new RuntimeException('Could not prepare the temporary OpenSSL configuration.');
        }

        try {
            $key = openssl_pkey_new([
                'config' => $configPath,
                'ec' => ['curve_name' => 'prime256v1'],
            ]);
        } finally {
            @unlink($configPath);
        }

        if (! $key) {
            throw new RuntimeException('OpenSSL could not create an EC key.');
        }

        if (! openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('OpenSSL could not export the EC private key.');
        }
        $details = openssl_pkey_get_details($key);
        if (! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('OpenSSL did not return the EC public key coordinates.');
        }
        $d = $details['ec'];
        $public = self::b64("\x04".str_pad($d['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['y'], 32, "\0", STR_PAD_LEFT));

        Setting::put('push.vapid_private', encrypt($pem));
        Setting::put('push.vapid_public', $public);
        PushSubscription::query()->delete();

        return $public;
    }

    /** Ping every device of this user. Returns how many were reached. */
    public function toUser(User $user): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $sent = 0;
        foreach (PushSubscription::where('user_id', $user->id)->get() as $sub) {
            try {
                $res = Http::timeout(10)->withHeaders([
                    'TTL' => '86400',
                    'Urgency' => 'normal',
                    'Authorization' => 'vapid t='.$this->jwt($sub->endpoint).', k='.self::publicKey(),
                ])->withBody('', 'application/octet-stream')->post($sub->endpoint);

                if (in_array($res->status(), [404, 410], true)) {
                    $sub->delete(); // unsubscribed / expired
                } elseif ($res->successful()) {
                    $sub->forceFill(['last_sent_at' => now()])->save();
                    $sent++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    private function jwt(string $endpoint): string
    {
        $p = parse_url($endpoint);
        $header = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64(json_encode([
            'aud' => $p['scheme'].'://'.$p['host'],
            'exp' => time() + 12 * 3600,
            'sub' => 'mailto:'.(setting('contact.email') ?: 'admin@'.parse_url(config('app.url'), PHP_URL_HOST)),
        ]));

        $pem = decrypt((string) setting('push.vapid_private'));
        if (! openssl_sign($header.'.'.$claims, $der, $pem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the VAPID token.');
        }

        return $header.'.'.$claims.'.'.self::b64(self::derToRaw($der));
    }

    /** OpenSSL gives DER (SEQUENCE of two INTEGERs); JWT needs r||s, 32 bytes each. */
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7F;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
