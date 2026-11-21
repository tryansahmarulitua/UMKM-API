<?php

namespace App\Support;

/** Indonesian mobile numbers, typed as 0812..., 62812... or +62 812-..., stored as 62812... */
class NomorWhatsApp
{
    /**
     * A mobile number (08... or 628...); spaces and dashes are allowed. At most 11 digits after 8x,
     * so the stored 62... form fits the VARCHAR(15) columns.
     */
    public const REGEX = '/^(\+62|62|0)[\s-]?8[1-9](?:[\s-]?[0-9]){6,11}$/';

    public static function rapikan(?string $nomor): ?string
    {
        if ($nomor === null || $nomor === '') {
            return $nomor;
        }
        $digit = preg_replace('/\D/', '', $nomor);

        return str_starts_with($digit, '0') ? '62'.substr($digit, 1) : $digit;
    }
}
