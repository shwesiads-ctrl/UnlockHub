<?php

namespace App\Support;

/** File names that are safe to put in an HTTP header or on disk. */
class SafeName
{
    /** For Content-Disposition: no quotes, backslashes, control characters or non-ASCII. */
    public static function header(string $name): string
    {
        $name = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $name) ?? 'file';

        return trim($name) !== '' ? mb_substr($name, 0, 140) : 'file';
    }

    /** Extensions a web server might execute or a browser might run as a page. */
    public const DANGEROUS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'inc',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'jspx', 'shtml', 'htaccess', 'htpasswd',
        'html', 'htm', 'xhtml', 'svg', 'svgz', 'js', 'mjs', 'xml', 'swf', 'hta', 'user.ini', 'ini',
    ];

    /** True if any part of the name ("x.php.zip" → php, zip) is a dangerous extension. */
    public static function isDangerous(string $name): bool
    {
        $parts = array_slice(explode('.', strtolower(basename($name))), 1);
        if (str_starts_with(strtolower(basename($name)), '.')) {
            return true; // .htaccess, .user.ini …
        }

        return (bool) array_intersect($parts, self::DANGEROUS);
    }
}
