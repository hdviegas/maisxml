<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\FiscalDocument;
use App\Models\User;
use App\Services\Tenant\ActiveOrganizationSession;
use Illuminate\Support\Facades\Schema;

class ActivityLogService
{
    public function record(
        ?User $user,
        string $action,
        ?FiscalDocument $document = null,
        array $properties = [],
        ?int $organizationId = null,
    ): void {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        $resolvedOrg = $organizationId ?? $document?->organization_id;
        if ($resolvedOrg === null && $user !== null) {
            $resolvedOrg = ActiveOrganizationSession::scopedOrganizationIds($user)->first();
        }

        ActivityLog::query()->create([
            'user_id' => $user?->id,
            'organization_id' => $resolvedOrg,
            'action' => $action,
            'subject_type' => $document ? FiscalDocument::class : null,
            'subject_id' => $document?->id,
            'properties' => $properties === [] ? null : $properties,
            'created_at' => now(),
        ]);
    }
}
