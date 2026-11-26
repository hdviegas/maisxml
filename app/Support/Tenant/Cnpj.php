<?php

namespace App\Support\Tenant;

final class Cnpj
{
    /** CNPJ (14 digits) or CPF (11 digits) with punctuation; anything else is returned as digits. */
    public static function format(?string $value): string
    {
        $d = self::digits($value);

        return match (strlen($d)) {
            14 => preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $d),
            11 => preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $d),
            default => $d,
        };
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
