<?php

namespace App\Support;

/** Indonesian mobile numbers, typed as 0812..., 62812... or +62 812-..., stored as 62812... */
class NomorWhatsApp
{
    /** A mobile number (08... or 628...); spaces and dashes are allowed. */
    public const REGEX = '/^(\+62|62|0)[\s-]?8[1-9][0-9\s-]{6,14}$/';

    public static function rapikan(?string $nomor): ?string
    {
        if ($nomor === null || $nomor === '') {
            return $nomor;
        }
        $digit = preg_replace('/\D/', '', $nomor);

        return str_starts_with($digit, '0') ? '62'.substr($digit, 1) : $digit;
    }
}
