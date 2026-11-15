<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** 15-digit IMEI with a valid Luhn check digit. */
class Imei implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('The :attribute must be a valid 15-digit IMEI.');
        }
    }

    public static function isValid(string $imei): bool
    {
        if (! preg_match('/^\d{15}$/', $imei)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $d = (int) $imei[$i];
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return $sum % 10 === 0;
    }
}
