<?php

namespace App\Services\Sieg;

use App\Services\Sieg\Exceptions\SiegDeadlineException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * Espaçamento mínimo entre chamadas por (API Key, endpoint), compartilhado via
 * cache + lock atômico (vale entre processos e servidores).
 */
class SiegRateLimiter
{
    private const STATE_TTL_SECONDS = 86400;

    public function acquire(string $limiterKey, string $endpoint, ?CarbonInterface $deadline = null): void
    {
        $nowMs = $this->nowMs();
        $deadlineMs = $deadline !== null ? (int) round($deadline->getPreciseTimestamp(3)) : null;
        $interval = $this->intervalMs($endpoint);
        $stateKey = "sieg:rl:$limiterKey:$endpoint";

        $slot = Cache::lock("sieg:rl:lock:$limiterKey:$endpoint", 10)->block(5, function () use ($stateKey, $nowMs, $deadlineMs, $interval, $endpoint) {
            $state = Cache::get($stateKey, []);
            $slot = max($nowMs, (int) ($state['next_allowed_at'] ?? 0), (int) ($state['cooldown_until'] ?? 0));

            if ($slot > $nowMs && $deadlineMs !== null && $slot > $deadlineMs) {
                // Nada foi reservado ainda: apenas recusa.
                throw new SiegDeadlineException("Aguardar o limite de taxa de '$endpoint' ultrapassaria o prazo da execução.");
            }

            $state['next_allowed_at'] = $slot + $interval;
            Cache::put($stateKey, $state, self::STATE_TTL_SECONDS);

            return $slot;
        });

        if ($slot > $nowMs) {
            Sleep::for($slot - $nowMs)->milliseconds();
        }
    }

    /**
     * Registra cooldown após 429/5xx e devolve os segundos de espera.
     */
    public function penalize(string $limiterKey, string $endpoint, ?int $retryAfterSeconds, int $attempt): int
    {
        if ($retryAfterSeconds !== null) {
            $wait = max(0, $retryAfterSeconds);
        } else {
            $base = (int) config('sieg.backoff.base_seconds');
            $max = (int) config('sieg.backoff.max_seconds');
            $exp = min($max, $base * (2 ** max(0, $attempt - 1)));
            $wait = (int) ceil($exp * (1 + random_int(0, 20) / 100));
        }

        $stateKey = "sieg:rl:$limiterKey:$endpoint";
        Cache::lock("sieg:rl:lock:$limiterKey:$endpoint", 10)->block(5, function () use ($stateKey, $wait) {
            $state = Cache::get($stateKey, []);
            $state['cooldown_until'] = max((int) ($state['cooldown_until'] ?? 0), $this->nowMs() + $wait * 1000);
            Cache::put($stateKey, $state, self::STATE_TTL_SECONDS);
        });

        return $wait;
    }

    private function intervalMs(string $endpoint): int
    {
        $limit = (float) config("sieg.rate_limits.$endpoint", 1);
        $effective = max(1.0, $limit * (float) config('sieg.rate_limit_safety'));

        return (int) round(60000 / $effective);
    }

    private function nowMs(): int
    {
        return (int) round(now()->getPreciseTimestamp(3));
    }
}
