<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Domain lock: one serial key = one domain (www. and subdomains included).
 * The last signed answer from the license server is kept in storage/app/private/license.json.
 */
class LicenseService
{
    private const FILE = 'license.json';

    public static function enabled(): bool
    {
        return trim((string) config('license.public_key')) !== '';
    }

    /** The domain this install runs on (APP_URL), without www. */
    public static function host(): ?string
    {
        return self::normalize((string) config('app.url'));
    }

    public static function normalize(?string $v): ?string
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        $host = parse_url(str_contains($v, '://') ? $v : 'http://'.$v, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }
        $host = rtrim($host, '.');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public static function isDevHost(?string $host): bool
    {
        $host = trim((string) $host, '[]');

        return $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || (bool) preg_match('/\.(test|local|localhost|invalid|example)$/', $host)
            || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
    }

    /** Local testing (XAMPP, php artisan serve) never needs a key — but only when the visitor is local too. */
    public static function isDevInstall(?Request $request = null): bool
    {
        return self::isDevHost(self::host()) && ($request === null || self::isDevHost(self::normalize($request->getHost())));
    }

    public static function covers(?string $bound, ?string $host): bool
    {
        $bound = self::normalize($bound);
        $host = self::normalize($host);

        return $bound && $host && ($host === $bound || str_ends_with($host, '.'.$bound));
    }

    public static function serial(): ?string
    {
        try {
            $s = setting('license.key');
        } catch (Throwable) {
            $s = null;
        }
        $s = $s ?: config('license.key');

        return $s ? strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $s)) : null;
    }

    public static function installId(): string
    {
        try {
            if (! ($id = setting('license.install_id'))) {
                $id = (string) Str::uuid();
                Setting::put('license.install_id', $id);
            }

            return (string) $id;
        } catch (Throwable) {
            return 'unknown';
        }
    }

    /** Verified payload of the stored answer, or null. */
    public static function token(): ?array
    {
        $path = storage_path('app/private/'.self::FILE);
        if (! is_file($path)) {
            return null;
        }
        $raw = json_decode((string) @file_get_contents($path), true);
        if (! is_array($raw) || empty($raw['payload']) || empty($raw['signature'])) {
            return null;
        }

        return self::verify($raw['payload'], $raw['signature']);
    }

    public static function verify(string $payloadB64, string $signatureB64): ?array
    {
        $json = base64_decode($payloadB64, true);
        $sig = base64_decode($signatureB64, true);
        $pub = base64_decode((string) config('license.public_key'), true);
        if ($json === false || $sig === false || $pub === false
            || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }
        try {
            if (! sodium_crypto_sign_verify_detached($sig, $json, $pub)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{valid: bool, reason: string, message: string, data: ?array, refresh_due: bool}
     */
    public static function status(?Request $request = null): array
    {
        $out = fn (bool $valid, string $reason, string $message = '', ?array $data = null, bool $due = false) => compact('valid', 'reason', 'message', 'data') + ['refresh_due' => $due];

        if (! self::enabled()) {
            return $out(true, 'not_configured', 'The license lock is not set up (no public key in config/license.php).');
        }
        if (self::isDevInstall($request)) {
            return $out(true, 'dev', 'Local test install — no license needed.');
        }
        $serial = self::serial();
        if (! $serial) {
            return $out(false, 'missing', 'Enter your serial key to activate this site.');
        }
        $t = self::token();
        if (! $t || ($t['serial'] ?? '') !== $serial) {
            return $out(false, 'not_activated', 'This serial key has not been activated on this domain yet.', null, true);
        }
        if (($t['status'] ?? '') !== 'active') {
            return $out(false, (string) $t['status'], (string) ($t['message'] ?? 'License not active.'), $t, true);
        }
        if (! self::covers($t['domain'] ?? null, self::host())) {
            return $out(false, 'domain', 'This key is activated for '.($t['domain'] ?? '?').', not '.self::host().'.', $t, true);
        }
        if (! empty($t['expires_at']) && strtotime($t['expires_at'].' UTC') < time()) {
            return $out(false, 'expired', 'This license has expired. Renew it with the seller.', $t, true);
        }
        $graceEnd = (int) ($t['valid_until'] ?? 0) + (int) config('license.grace_days', 7) * 86400;
        if (time() > $graceEnd) {
            return $out(false, 'offline', 'The license server could not be reached for too long. Check the server can make outgoing HTTPS requests, then press "Check now".', $t, true);
        }

        $due = time() > (int) ($t['issued_at'] ?? 0) + (int) config('license.check_hours', 24) * 3600;

        return $out(true, 'active', '', $t, $due);
    }

    public static function valid(?Request $request = null): bool
    {
        return self::status($request)['valid'];
    }

    /**
     * Ask the server. A signed answer is stored whatever it says (so "suspended" sticks);
     * network errors leave the old answer in place.
     *
     * @return array{ok: bool, message: string, data: ?array}
     */
    public static function call(string $action, ?string $serial = null): array
    {
        $serial ??= self::serial();
        $host = self::host();
        if (! $serial || ! $host) {
            return ['ok' => false, 'message' => 'Serial key or site address (APP_URL) missing.', 'data' => null];
        }

        try {
            $res = Http::asForm()->acceptJson()->timeout(8)->connectTimeout(5)
                ->post(rtrim((string) config('license.server'), '/').'/api/v1/'.$action, [
                    'serial' => $serial,
                    'domain' => $host,
                    'version' => config('unlockhub.version', '1.0'),
                    'install_id' => self::installId(),
                ]);
        } catch (Throwable $e) {
            Log::warning('License server unreachable: '.$e->getMessage());

            return ['ok' => false, 'message' => 'Could not reach the license server. Try again in a minute.', 'data' => null];
        }

        $body = $res->json();
        if (! is_array($body)) {
            return ['ok' => false, 'message' => 'The license server gave an unexpected answer (HTTP '.$res->status().').', 'data' => null];
        }

        if ($action === 'deactivate') {
            return ['ok' => (bool) ($body['ok'] ?? false), 'message' => (string) ($body['message'] ?? ''), 'data' => null];
        }

        if (! empty($body['payload']) && ! empty($body['signature'])) {
            $data = self::verify($body['payload'], $body['signature']);
            if (! $data || ($data['serial'] ?? '') !== $serial) {
                return ['ok' => false, 'message' => 'The answer was not signed by your license server (wrong public key in config/license.php?).', 'data' => null];
            }
            self::store($body['payload'], $body['signature']);

            return ['ok' => ($data['status'] ?? '') === 'active', 'message' => (string) ($data['message'] ?? ''), 'data' => $data];
        }

        return ['ok' => false, 'message' => (string) ($body['message'] ?? 'License check failed.'), 'data' => null];
    }

    public static function activate(string $serial): array
    {
        $serial = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $serial));
        $r = self::call('activate', $serial);
        if ($r['ok']) {
            Setting::put('license.key', $serial);
        }

        return $r;
    }

    public static function refresh(): array
    {
        $r = self::call('check');
        try {
            Setting::put('license.checked_at', now()->toIso8601String());
        } catch (Throwable) {
        }

        return $r;
    }

    public static function deactivate(): array
    {
        $r = self::call('deactivate');
        if ($r['ok']) {
            @unlink(storage_path('app/private/'.self::FILE));
            Setting::put('license.key', null);
        }

        return $r;
    }

    private static function store(string $payload, string $signature): void
    {
        $dir = storage_path('app/private');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($dir.'/'.self::FILE, json_encode(['payload' => $payload, 'signature' => $signature, 'saved_at' => time()]), LOCK_EX);
    }
}
