<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/** Google Authenticator (TOTP) helpers. */
class TwoFactorService
{
    public function __construct(private Google2FA $google2fa = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(
            (string) setting('site.name', config('app.name')),
            $user->email,
            $secret
        );

        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);

        return strlen($code) === 6 && (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }
}
