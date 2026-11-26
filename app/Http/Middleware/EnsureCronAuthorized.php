<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege os endpoints de cron por token (header X-Cron-Token ou Bearer).
 * Qualquer falha responde 404 para não revelar que o endpoint existe.
 */
class EnsureCronAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('sieg.cron.token');
        if (! is_string($token) || $token === '') {
            abort(404);
        }

        $given = $request->header('X-Cron-Token');
        if (! is_string($given) || $given === '') {
            $given = $request->bearerToken();
        }

        if (! is_string($given) || $given === '' || ! hash_equals($token, $given)) {
            abort(404);
        }

        return $next($request);
    }
}
