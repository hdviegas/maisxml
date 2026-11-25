<?php

use App\Models\Branch;
use App\Models\Organization;
use App\Models\SiegAccount;
use App\Models\SiegSyncRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const SYNCCMD_KEY = 'comando-super-secreto-key-987';

function synccmd_account(array $attrs = []): SiegAccount
{
    $org = Organization::query()->create(['social' => 'Org', 'fantasia' => 'Org', 'cnpj' => '11222333000181']);
    Branch::query()->create(['empresa' => $org->id, 'social' => 'M', 'fantasia' => null, 'cnpj' => '11222333000181', 'entrada' => true]);

    return SiegAccount::factory()->create(['organization_id' => $org->id, 'name' => 'Conta Teste', 'api_key' => SYNCCMD_KEY] + $attrs);
}

beforeEach(function () {
    Cache::flush();
    Sleep::fake();
    config(['sieg.rate_limits' => array_map(fn () => 6000, config('sieg.rate_limits')), 'sieg.backoff.max_retries' => 1]);
});

test('sieg:sync prints a table per account and succeeds', function () {
    synccmd_account();
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 0, 'UltimoNsu' => 0])]);

    $this->artisan('sieg:sync')
        ->expectsOutputToContain('Conta Teste')
        ->expectsOutputToContain('importados')
        ->doesntExpectOutputToContain(SYNCCMD_KEY)
        ->assertSuccessful();

    expect(SiegSyncRun::query()->sole()->status)->toBe('ok');
});

test('sieg:sync exits with failure when a run failed', function () {
    synccmd_account();
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response('', 403)]);

    $this->artisan('sieg:sync')->expectsOutputToContain('failed')->assertFailed();
});

test('sieg:sync respects pause, --force, --account and never runs disabled accounts', function () {
    $paused = synccmd_account(['paused_until' => now()->addHour()]);
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 0, 'UltimoNsu' => 0])]);

    $this->artisan('sieg:sync')->expectsOutputToContain('Nenhuma conta SIEG ativa')->assertSuccessful();
    expect(SiegSyncRun::query()->count())->toBe(0);

    $this->artisan('sieg:sync', ['--account' => $paused->id])->expectsOutputToContain('skipped')->assertSuccessful();
    expect(SiegSyncRun::query()->sole()->status)->toBe('skipped');

    $this->artisan('sieg:sync', ['--account' => $paused->id, '--force' => true])->assertSuccessful();
    expect(SiegSyncRun::query()->latest('id')->first()->status)->toBe('ok');

    $paused->update(['enabled' => false]);
    $this->artisan('sieg:sync', ['--account' => $paused->id, '--force' => true])->expectsOutputToContain('skipped')->assertSuccessful();
    expect(SiegSyncRun::query()->latest('id')->first()->message)->toContain('desabilitada');
});

test('sieg:sync fails for an unknown account', function () {
    $this->artisan('sieg:sync', ['--account' => 999])->assertFailed();
});

test('the scheduler registers sieg:sync only when enabled', function () {
    $count = fn () => collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'sieg:sync'))->count();
    $before = $count();

    config(['sieg.schedule.enabled' => false]);
    require base_path('routes/console.php');
    expect($count())->toBe($before);

    config(['sieg.schedule.enabled' => true, 'sieg.schedule.cron' => '*/7 * * * *']);
    require base_path('routes/console.php');
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'sieg:sync'));
    expect($count())->toBe($before + 1)
        ->and($event->expression)->toBe('*/7 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->description)->toBe('Sincroniza XMLs do SIEG');
});

test('sieg:probe asks for confirmation and sends nothing on no', function () {
    $account = synccmd_account();
    Http::fake();

    $this->artisan('sieg:probe', ['account' => $account->id])
        ->expectsConfirmation('Deseja continuar?', 'no')
        ->doesntExpectOutputToContain(SYNCCMD_KEY)
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('sieg:probe --yes runs, saves the report and never prints the key', function () {
    $account = synccmd_account();
    Http::fake([
        '*/create-jwt' => Http::response('jwt-secret-token', 200),
        '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 1, 'UltimoNsu' => 2]),
        '*/baixar-xmls' => Http::response('', 404),
    ]);
    $pattern = storage_path("logs/sieg-probe-{$account->id}-*.json");
    foreach (File::glob($pattern) as $f) {
        File::delete($f);
    }

    $this->artisan('sieg:probe', ['account' => $account->id, '--yes' => true])
        ->expectsOutputToContain('create-jwt')
        ->doesntExpectOutputToContain(SYNCCMD_KEY)
        ->doesntExpectOutputToContain('jwt-secret-token')
        ->assertSuccessful();

    Http::assertSentCount(3);
    $files = File::glob($pattern);
    expect($files)->toHaveCount(1);
    $content = File::get($files[0]);
    expect(json_decode($content, true))->toHaveKeys(['create-jwt', 'consultar-nsu', 'baixar-xmls'])
        ->and($content)->not->toContain(SYNCCMD_KEY)->not->toContain('jwt-secret-token');
    File::delete($files[0]);
});
