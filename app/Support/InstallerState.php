<?php

namespace App\Support;

/** Plain PHP: the installer must also work before Composer/Laravel is available. */
final class InstallerState
{
    public static function claim(string $lock): void
    {
        $handle = @fopen($lock, 'x');
        if ($handle === false) {
            throw new \RuntimeException('Setup is locked or storage is not writable. Contact the server administrator.');
        }
        fwrite($handle, 'installation started '.date('c')."\n");
        fclose($handle);
    }

    public static function isLocked(string $lock, string $env): bool
    {
        if (is_file($lock)) {
            return true;
        }
        if (!is_file($env)) {
            return false;
        }
        $content = @file_get_contents($env);
        if ($content === false) {
            return true; // An unreadable configuration is never proof of a fresh install.
        }
        if (preg_match('/^APP_KEY=(.*)$/m', $content, $match)) {
            return trim($match[1], " \t\"'\r") !== '';
        }

        return false;
    }
}
