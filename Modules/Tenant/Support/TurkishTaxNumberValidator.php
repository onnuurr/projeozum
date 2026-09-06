<?php

namespace Modules\Tenant\Support;

/**
 * VKN (10 hane) ve TCKN (11 hane) için resmi checksum algoritmaları — dış servise
 * gitmeden anlık biçim doğrulaması yapar. Unvan/kimlik eşleşmesini DOĞRULAMAZ (bunun
 * için ücretsiz genel-erişimli bir kaynak yok, bkz. proje planı) — sadece yazım
 * hatalarını yakalar. JS karşılığı: Modules/Tenant/Resources/assets/js/utils/turkishTaxNumber.js
 * — iki tarafı da senkron tutulmalı.
 */
class TurkishTaxNumberValidator
{
    public static function isValid(?string $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        return match (strlen($digits)) {
            10 => self::isValidVkn($digits),
            11 => self::isValidTckn($digits),
            default => false,
        };
    }

    public static function isValidVkn(string $vkn): bool
    {
        if (! preg_match('/^\d{10}$/', $vkn)) {
            return false;
        }

        $d = array_map('intval', str_split($vkn));
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $tmp = ($d[$i] + 9 - $i) % 10;
            $tmp = ($tmp * (2 ** (9 - $i))) % 9;
            if ($d[$i] !== 0 && $tmp === 0) {
                $tmp = 9;
            }
            $sum += $tmp;
        }

        $check = (10 - ($sum % 10)) % 10;

        return $check === $d[9];
    }

    public static function isValidTckn(string $tckn): bool
    {
        if (! preg_match('/^[1-9]\d{10}$/', $tckn)) {
            return false;
        }

        $d = array_map('intval', str_split($tckn));

        $oddSum  = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $evenSum = $d[1] + $d[3] + $d[5] + $d[7];
        $d10 = (((($oddSum * 7) - $evenSum) % 10) + 10) % 10;

        if ($d10 !== $d[9]) {
            return false;
        }

        $d11 = array_sum(array_slice($d, 0, 10)) % 10;

        return $d11 === $d[10];
    }
}
