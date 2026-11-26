<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class IssuedPeriod
{
    /**
     * Default listing/dashboard window: the last N days up to today (inclusive), app timezone.
     *
     * @return array{0: string, 1: string} [from, to] as Y-m-d
     */
    public static function defaultRange(): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $days = max(1, (int) config('dashboard.default_period_days', 30));
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return [$today->subDays($days - 1)->toDateString(), $today->toDateString()];
    }

    /** Strict Y-m-d parser; null for anything else (including impossible dates). */
    public static function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
