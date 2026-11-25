<?php

use App\Models\Branch;
use App\Models\Organization;
use App\Models\SiegAccount;
use App\Models\SiegSyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const SIEG_KEY = 'super-secret-sieg-key-1234567890';

function sieg_org(string $cnpj = '11222333000181', string $name = 'Sacolao'): Organization
{
    $org = Organization::query()->create(['social' => $name.' LTDA', 'fantasia' => $name, 'cnpj' => $cnpj]);
    Branch::query()->create(['empresa' => $org->id, 'social' => $name.' Matriz', 'fantasia' => null, 'cnpj' => $cnpj, 'entrada' => true]);

    return $org;
}

test('creates account via command, stores key encrypted and never prints it', function () {
    $org = sieg_org();

    $this->artisan('sieg:account', ['organization' => (string) $org->id])
        ->expectsQuestion('API Key SIEG (não será exibida)', SIEG_KEY)
        ->doesntExpectOutputToContain(SIEG_KEY)
        ->assertSuccessful();

    $account = SiegAccount::query()->firstOrFail();
    expect($account->api_key)->toBe(SIEG_KEY)
        ->and($account->name)->toBe('Sacolao')
        ->and($account->mode)->toBe('nsu')
        ->and($account->enabled)->toBeTrue();

    $raw = DB::table('sieg_accounts')->value('api_key');
    expect($raw)->not->toBe(SIEG_KEY)->and($raw)->not->toContain(SIEG_KEY);
    expect($account->connection()->apiKey)->toBe(SIEG_KEY);
});

test('resolves organization by CNPJ, formatted or via branch', function () {
    $org = sieg_org('11222333000181');

    $this->artisan('sieg:account', ['organization' => '11.222.333/0001-81', '--name' => 'X'])
        ->expectsQuestion('API Key SIEG (não será exibida)', SIEG_KEY)
        ->assertSuccessful();

    expect(SiegAccount::query()->where('organization_id', $org->id)->count())->toBe(1);

    // Matriz diferente da filial: resolve pela filial.
    $org2 = Organization::query()->create(['social' => 'Outra', 'fantasia' => 'Outra', 'cnpj' => '99999999000199']);
    Branch::query()->create(['empresa' => $org2->id, 'social' => 'Filial', 'cnpj' => '55444333000122', 'entrada' => true]);

    $this->artisan('sieg:account', ['organization' => '55444333000122'])
        ->expectsQuestion('API Key SIEG (não será exibida)', SIEG_KEY)
        ->assertSuccessful();
    expect(SiegAccount::query()->where('organization_id', $org2->id)->exists())->toBeTrue();
});

test('unknown organization fails and nothing is created', function () {
    $this->artisan('sieg:account', ['organization' => '999'])->assertFailed();
    $this->artisan('sieg:account', ['organization' => '12345678000195'])->assertFailed();

    expect(SiegAccount::query()->count())->toBe(0);
});

test('ambiguous CNPJ fails', function () {
    sieg_org('11222333000181', 'A');
    sieg_org('11222333000181', 'B');

    $this->artisan('sieg:account', ['organization' => '11222333000181'])->assertFailed();
    expect(SiegAccount::query()->count())->toBe(0);
});

test('short key is rejected', function () {
    $org = sieg_org();

    $this->artisan('sieg:account', ['organization' => (string) $org->id])
        ->expectsQuestion('API Key SIEG (não será exibida)', 'curta')
        ->assertFailed();

    expect(SiegAccount::query()->count())->toBe(0);
});

test('rotate-key replaces the key and clears the pause', function () {
    $org = sieg_org();
    $account = SiegAccount::factory()->create([
        'organization_id' => $org->id, 'name' => 'Sacolao', 'consecutive_failures' => 5,
        'paused_until' => now()->addHour(), 'last_error' => 'x',
    ]);
    $new = 'another-secret-key-abcdef123456';

    $this->artisan('sieg:account', ['organization' => (string) $org->id, '--rotate-key' => true])
        ->expectsQuestion('API Key SIEG (não será exibida)', $new)
        ->doesntExpectOutputToContain($new)
        ->assertSuccessful();

    $account->refresh();
    expect($account->api_key)->toBe($new)
        ->and($account->consecutive_failures)->toBe(0)
        ->and($account->paused_until)->toBeNull();
    expect(SiegAccount::query()->count())->toBe(1);
});

test('existing account is updated without asking for the key', function () {
    $org = sieg_org();
    $account = SiegAccount::factory()->create(['organization_id' => $org->id, 'name' => 'Sacolao']);
    $key = $account->api_key;

    $this->artisan('sieg:account', ['organization' => (string) $org->id, '--disable' => true])->assertSuccessful();
    expect($account->refresh()->enabled)->toBeFalse()->and($account->api_key)->toBe($key);
    expect(SiegAccount::runnable()->count())->toBe(0);

    $this->artisan('sieg:account', ['organization' => (string) $org->id, '--enable' => true, '--mode' => 'periodo'])->assertSuccessful();
    expect($account->refresh()->enabled)->toBeTrue()->and($account->mode)->toBe('periodo');
    expect(SiegAccount::runnable()->count())->toBe(1);
});

test('sieg:accounts masks the key', function () {
    $org = sieg_org();
    $account = SiegAccount::factory()->create(['organization_id' => $org->id, 'name' => 'Sacolao', 'api_key' => SIEG_KEY]);
    SiegSyncRun::query()->create(['sieg_account_id' => $account->id, 'mode' => 'nsu', 'status' => 'ok', 'imported' => 7]);

    Artisan::call('sieg:accounts');
    $out = Artisan::output();

    expect($out)->toContain('Sacolao')->toContain('••••7890')->toContain('imp=7')
        ->not->toContain(SIEG_KEY);
});

test('recordFailure pauses after the threshold and recordSuccess resets', function () {
    config(['sieg.sync.pause_after_failures' => 3, 'sieg.sync.pause_minutes' => 30]);
    $account = SiegAccount::factory()->create();

    $account->recordFailure('falha 1');
    $account->recordFailure('falha 2');
    expect($account->isPaused())->toBeFalse();

    $account->recordFailure(str_repeat('e', 2000));
    expect($account->isPaused())->toBeTrue()
        ->and(strlen($account->last_error))->toBe(1000)
        ->and($account->consecutive_failures)->toBe(3);
    expect(SiegAccount::runnable()->count())->toBe(0);

    $account->recordSuccess();
    expect($account->consecutive_failures)->toBe(0)->and($account->last_error)->toBeNull()
        ->and($account->last_success_at)->not->toBeNull();
});

test('api key never leaks through serialization', function () {
    $account = SiegAccount::factory()->create(['api_key' => SIEG_KEY]);

    expect($account->toArray())->not->toHaveKey('api_key')
        ->and($account->toJson())->not->toContain(SIEG_KEY)
        ->and((string) $account)->not->toContain(SIEG_KEY)
        ->and(print_r($account, true))->not->toContain(SIEG_KEY)
        ->and($account->maskedKey())->toBe('••••7890');
});

test('sync run bump is in-memory and finish persists', function () {
    $account = SiegAccount::factory()->create();
    $run = SiegSyncRun::query()->create(['sieg_account_id' => $account->id, 'mode' => 'nsu', 'status' => 'running', 'started_at' => now()]);

    $run->bump('imported', 3);
    $run->bump('errors');
    expect(SiegSyncRun::query()->find($run->id)->imported)->toBe(0);

    $run->finish('partial', 'ok');
    $fresh = SiegSyncRun::query()->find($run->id);
    expect($fresh->imported)->toBe(3)->and($fresh->errors)->toBe(1)
        ->and($fresh->status)->toBe('partial')->and($fresh->finished_at)->not->toBeNull();

    expect(fn () => $run->bump('bogus'))->toThrow(InvalidArgumentException::class);
});

test('migrations are idempotent when tables already exist', function () {
    expect(Schema::hasTable('sieg_accounts'))->toBeTrue();

    $this->artisan('migrate')->assertSuccessful();

    foreach (glob(database_path('migrations/2026_11_25_10000*_create_sieg_*.php')) as $file) {
        $migration = require $file;
        $migration->up();
    }

    expect(Schema::hasTable('sieg_accounts'))->toBeTrue()
        ->and(Schema::hasTable('sieg_sync_cursors'))->toBeTrue()
        ->and(Schema::hasTable('sieg_sync_runs'))->toBeTrue();
});
