<?php

namespace App\Support;

use Throwable;

/**
 * SMTP from Admin › Settings (overrides MAIL_* in .env when a host is saved).
 * Password is stored encrypted.
 */
class MailSettings
{
    public static function apply(): void
    {
        try {
            $host = setting('mail.host');
        } catch (Throwable) {
            return; // before install / migrations
        }
        if (! $host) {
            return;
        }

        $password = null;
        try {
            $password = setting('mail.password') ? decrypt(setting('mail.password')) : null;
        } catch (Throwable) {
        }

        $encryption = setting('mail.encryption', 'tls');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $host,
                'port' => (int) setting('mail.port', 587),
                'username' => setting('mail.username') ?: null,
                'password' => $password,
                'timeout' => 20,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
            'mail.from.address' => setting('mail.from_address') ?: config('mail.from.address'),
            'mail.from.name' => setting('mail.from_name') ?: setting('site.name', config('app.name')),
        ]);
    }
}
