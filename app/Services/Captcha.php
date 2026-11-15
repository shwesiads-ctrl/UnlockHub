<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Bot protection with Google reCAPTCHA (v2 checkbox / v3 invisible) or Cloudflare Turnstile.
 * Only enforced after the admin has passed the Test on the settings page, so a wrong key
 * can never lock everyone (including the admin) out of the login form.
 */
class Captcha
{
    public const PROVIDERS = [
        'none' => 'Off',
        'turnstile' => 'Cloudflare Turnstile',
        'recaptcha_v2' => 'Google reCAPTCHA v2 (checkbox)',
        'recaptcha_v3' => 'Google reCAPTCHA v3 (invisible score)',
    ];

    public const FORMS = [
        'login' => 'Login',
        'register' => 'Register',
        'password' => 'Forgot password',
        'contact' => 'Contact form',
        'ticket' => 'New support ticket',
    ];

    public static function provider(): string
    {
        $p = (string) setting('captcha.provider', 'none');

        return array_key_exists($p, self::PROVIDERS) ? $p : 'none';
    }

    public static function siteKey(): ?string
    {
        return setting('captcha.site_key') ?: null;
    }

    public static function secret(): ?string
    {
        try {
            return setting('captcha.secret') ? decrypt(setting('captcha.secret')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Keys saved and a test passed. */
    public static function active(): bool
    {
        return self::provider() !== 'none' && self::siteKey() && self::secret() && setting('captcha.verified_at');
    }

    public static function enabledFor(string $form): bool
    {
        return self::active() && setting('captcha.form.'.$form, in_array($form, ['login', 'register', 'password'], true) ? '1' : '0') === '1';
    }

    /** Throws a validation error unless the token checks out. */
    public static function check(Request $request, string $form): void
    {
        if (! self::enabledFor($form)) {
            return;
        }

        $result = self::verify($request, self::provider(), (string) self::secret(), $form);

        if (! $result['ok']) {
            throw ValidationException::withMessages(['captcha' => $result['message']]);
        }
    }

    /** @return array{ok: bool, message: string, raw: array} */
    public static function verify(Request $request, string $provider, string $secret, string $action): array
    {
        $token = (string) ($provider === 'turnstile' ? $request->input('cf-turnstile-response') : $request->input('g-recaptcha-response'));
        if ($token === '') {
            return ['ok' => false, 'message' => 'Please complete the security check.', 'raw' => []];
        }

        $url = $provider === 'turnstile'
            ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            : 'https://www.google.com/recaptcha/api/siteverify';

        try {
            $res = Http::asForm()->timeout(8)->post($url, ['secret' => $secret, 'response' => $token, 'remoteip' => $request->ip()])->json() ?? [];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'The security check service could not be reached. Please try again.', 'raw' => []];
        }

        if (empty($res['success'])) {
            return ['ok' => false, 'message' => 'Security check failed ('.implode(', ', (array) ($res['error-codes'] ?? ['invalid'])).'). Please try again.', 'raw' => $res];
        }

        if ($provider === 'recaptcha_v3') {
            $min = (float) setting('captcha.min_score', 0.5);
            if (($res['score'] ?? 0) < $min || (($res['action'] ?? $action) !== $action)) {
                return ['ok' => false, 'message' => 'Our bot check was not sure about this request. Please try again.', 'raw' => $res];
            }
        }

        return ['ok' => true, 'message' => 'ok', 'raw' => $res];
    }
}
