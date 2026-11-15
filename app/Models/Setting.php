<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** Per-request values (e.g. a reseller's shop name/logo) that win over the database. */
    private static array $overrides = [];

    public static function override(array $values): void
    {
        self::$overrides = $values + self::$overrides;
    }

    public static function clearOverrides(): void
    {
        self::$overrides = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key] ?? (in_array($key, ['site.name', 'pwa.theme_color', 'pwa.short_name'], true) ? $default : null);
        }

        $all = Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
