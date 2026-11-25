<?php

namespace App\Support\Tenant;

final class Cnpj
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function sqlNormalizeColumn(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE({$column}, '.', ''), '-', ''), '/', ''), ' ', '')";
    }
}
