<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\Tenant\ActiveOrganizationSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReportsPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $orgIds = ActiveOrganizationSession::scopedOrganizationIds($request->user());

        $logs = Collection::make();
        if (Schema::hasTable('activity_logs')) {
            $logs = ActivityLog::query()
                ->whereIn('organization_id', $orgIds)
                ->latest('created_at')
                ->limit(50)
                ->with('user')
                ->get();
        }

        return view('pages.reports', [
            'activityLogs' => $logs,
        ]);
    }
}
