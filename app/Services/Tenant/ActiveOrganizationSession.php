<?php

namespace App\Services\Tenant;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

final class ActiveOrganizationSession
{
    public static function scopedOrganizationIds(User $user): Collection
    {
        $ids = Organization::query()->forUser($user)->pluck('id');
        $raw = session('active_organization_id');

        if ($raw === null || $raw === '') {
            return $ids;
        }

        $active = (int) $raw;
        if (! $ids->contains($active)) {
            session()->forget('active_organization_id');

            return $ids;
        }

        return collect([$active]);
    }
}
