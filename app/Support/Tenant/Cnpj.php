<?php

namespace App\Support\Tenant;

final class Cnpj
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
