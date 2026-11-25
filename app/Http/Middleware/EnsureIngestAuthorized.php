<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIngestAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('ingest.token');
        $secret = config('ingest.hmac_secret');

        if (($token === null || $token === '') && ($secret === null || $secret === '')) {
            abort(503, __('ingest.disabled'));
        }

        $body = $request->getContent();

        if ($token !== null && $token !== '') {
            $given = $request->header('X-Ingest-Token', '');
            if (! is_string($given) || ! hash_equals($token, $given)) {
                abort(401, __('ingest.bad_token'));
            }
        }

        if ($secret !== null && $secret !== '') {
            $sigHeader = $request->header('X-Ingest-Signature', '');
            $expected = hash_hmac('sha256', $body, $secret);
            if (! is_string($sigHeader) || ! hash_equals($expected, strtolower($sigHeader))) {
                abort(401, __('ingest.bad_signature'));
            }
        }

        return $next($request);
    }
}
