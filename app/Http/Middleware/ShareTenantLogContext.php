<?php

namespace App\Http\Middleware;

use App\Services\Tenant\ActiveOrganizationSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ShareTenantLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $extra = [];

        if ($user = $request->user()) {
            $extra['user_id'] = $user->id;
            $extra['org_scope_ids'] = ActiveOrganizationSession::scopedOrganizationIds($user)->implode(',');
            if ($request->hasSession() && session()->has('active_organization_id')) {
                $extra['active_organization_id'] = (int) session('active_organization_id');
            }
        }

        if ($extra !== []) {
            Log::shareContext($extra);
        }

        return $next($request);
    }
}
