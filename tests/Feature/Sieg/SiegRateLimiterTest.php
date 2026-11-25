<?php

use App\Services\Sieg\Exceptions\SiegDeadlineException;
use App\Services\Sieg\SiegRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Cache::flush();
    $this->travelTo(now()->startOfSecond());
    Sleep::fake(syncWithCarbon: true);
    config(['sieg.rate_limits.baixar-xmls' => 2, 'sieg.rate_limit_safety' => 0.9]);
});

it('does not sleep on the first call and spaces the next ones', function () {
    $rl = new SiegRateLimiter;
    $rl->acquire('k', 'baixar-xmls');
    Sleep::assertNeverSlept();

    $start = now();
    $rl->acquire('k', 'baixar-xmls');
    Sleep::assertSleptTimes(1);
    expect(now()->diffInMilliseconds($start, true))->toBeBetween(33300, 33400);
});

it('keeps keys and endpoints independent', function () {
    $rl = new SiegRateLimiter;
    $rl->acquire('k1', 'baixar-xmls');
    $rl->acquire('k2', 'baixar-xmls');
    $rl->acquire('k1', 'contar-xmls');
    Sleep::assertNeverSlept();
});

it('throws deadline exception without reserving a slot', function () {
    $rl = new SiegRateLimiter;
    $rl->acquire('k', 'baixar-xmls');

    expect(fn () => $rl->acquire('k', 'baixar-xmls', now()->addSeconds(5)))->toThrow(SiegDeadlineException::class);

    // reserva não foi consumida: com prazo suficiente espera só ~33s
    $start = now();
    $rl->acquire('k', 'baixar-xmls', now()->addMinutes(5));
    expect(now()->diffInMilliseconds($start, true))->toBeBetween(33300, 33400);
});

it('penalize honors Retry-After and imposes cooldown', function () {
    $rl = new SiegRateLimiter;
    expect($rl->penalize('k', 'baixar-xmls', 30, 1))->toBe(30);

    $start = now();
    $rl->acquire('k', 'baixar-xmls');
    expect(now()->diffInSeconds($start, true))->toBeGreaterThanOrEqual(30);
});

it('penalize uses exponential backoff with up to 20 percent jitter capped by max', function () {
    config(['sieg.backoff.base_seconds' => 60, 'sieg.backoff.max_seconds' => 900]);
    $rl = new SiegRateLimiter;

    expect($rl->penalize('k', 'x', null, 1))->toBeBetween(60, 72)
        ->and($rl->penalize('k', 'x', null, 3))->toBeBetween(240, 288)
        ->and($rl->penalize('k', 'x', null, 10))->toBeBetween(900, 1080);
});
