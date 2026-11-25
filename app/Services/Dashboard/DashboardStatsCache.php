<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class DashboardStatsCache
{
    public static function cacheKey(User $user, string $issuedFrom, string $issuedTo): string
    {
        $ver = (int) Cache::get(self::versionKey($user), 0);

        return sprintf(
            'dashboard.stats.v3.%s.%d.%s.%s',
            $user->getAuthIdentifier(),
            $ver,
            $issuedFrom,
            $issuedTo,
        );
    }

    public static function forget(User $user): void
    {
        $k = self::versionKey($user);
        Cache::put($k, ((int) Cache::get($k, 0)) + 1, now()->addYear());
    }

    private static function versionKey(User $user): string
    {
        return 'dashboard.stats.ver.'.$user->getAuthIdentifier();
    }
}
