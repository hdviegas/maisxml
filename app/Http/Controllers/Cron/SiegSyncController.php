<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Services\Sieg\SiegSyncRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SiegSyncController extends Controller
{
    public function __invoke(SiegSyncRunner $runner): JsonResponse
    {
        $maxRuntime = max(1, (int) config('sieg.cron.max_runtime_seconds'));

        ignore_user_abort(true);
        @set_time_limit($maxRuntime + 30);

        $started = microtime(true);
        $results = $runner->runAll(now()->addSeconds($maxRuntime), false);

        $accounts = [];
        $ok = true;
        $totals = ['accounts' => 0, 'requests' => 0, 'downloaded' => 0, 'imported' => 0, 'errors' => 0];
        foreach ($results as $result) {
            $run = $result['run'];
            $ok = $ok && $run->status !== 'failed';
            $accounts[] = [
                'id' => $result['account']->id,
                'name' => $result['account']->name,
                'status' => $run->status,
                'requests' => (int) $run->requests,
                'downloaded' => (int) $run->downloaded,
                'imported' => (int) $run->imported,
                'duplicates' => (int) $run->duplicates,
                'ignored' => (int) $run->ignored,
                'events' => (int) $run->events,
                'errors' => (int) $run->errors,
                'message' => $run->message === null ? null : Str::limit((string) $run->message, 200),
            ];
            $totals['accounts']++;
            foreach (['requests', 'downloaded', 'imported', 'errors'] as $k) {
                $totals[$k] += (int) $run->{$k};
            }
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        Log::info('sieg.cron', $totals + ['ok' => $ok, 'duration_ms' => $durationMs]);

        return response()->json(['ok' => $ok, 'accounts' => $accounts, 'duration_ms' => $durationMs]);
    }
}
