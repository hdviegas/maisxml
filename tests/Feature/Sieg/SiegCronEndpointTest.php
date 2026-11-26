<?php

use App\Models\Branch;
use App\Models\Organization;
use App\Models\SiegAccount;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

const CRON_TOKEN = 'token-de-cron-de-teste-123';
const CRON_API_KEY = 'chave-sieg-super-secreta-555';

function cron_account(): SiegAccount
{
    $org = Organization::query()->create(['social' => 'Org', 'fantasia' => 'Org', 'cnpj' => '11222333000181']);
    Branch::query()->create(['empresa' => $org->id, 'social' => 'M', 'fantasia' => null, 'cnpj' => '11222333000181', 'entrada' => true]);

    return SiegAccount::factory()->create(['organization_id' => $org->id, 'name' => 'Conta Cron', 'api_key' => CRON_API_KEY]);
}

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('cron');
    Sleep::fake();
    config([
        'sieg.cron.token' => CRON_TOKEN,
        'sieg.rate_limits' => array_map(fn () => 6000, config('sieg.rate_limits')),
        'sieg.backoff.max_retries' => 1,
    ]);
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 0, 'UltimoNsu' => 0])]);
});

test('returns 404 when the token is not configured', function () {
    config(['sieg.cron.token' => null]);
    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => CRON_TOKEN])->assertNotFound();

    config(['sieg.cron.token' => '']);
    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => ''])->assertNotFound();
});

test('returns 404 with a wrong or missing token', function () {
    $this->postJson('/api/cron/sieg-sync')->assertNotFound();
    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => 'errado'])->assertNotFound();
    $this->postJson('/api/cron/sieg-sync', [], ['Authorization' => 'Bearer errado'])->assertNotFound();
});

test('never accepts the token in the query string', function () {
    $this->postJson('/api/cron/sieg-sync?token='.CRON_TOKEN)->assertNotFound();
    $this->getJson('/api/cron/sieg-sync?X-Cron-Token='.CRON_TOKEN)->assertNotFound();
});

test('returns 200 with an empty list when there are no runnable accounts', function () {
    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => CRON_TOKEN])
        ->assertOk()
        ->assertJson(['ok' => true, 'accounts' => []])
        ->assertJsonStructure(['duration_ms']);
});

test('accepts the Bearer token and both GET and POST', function () {
    $this->getJson('/api/cron/sieg-sync', ['Authorization' => 'Bearer '.CRON_TOKEN])->assertOk();
    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => CRON_TOKEN])->assertOk();
});

test('runs the sync for a runnable account and never leaks the api key', function () {
    $account = cron_account();

    $response = $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => CRON_TOKEN])->assertOk();

    $response->assertJson(['ok' => true])
        ->assertJsonPath('accounts.0.id', $account->id)
        ->assertJsonPath('accounts.0.name', 'Conta Cron')
        ->assertJsonPath('accounts.0.status', 'ok')
        ->assertJsonStructure(['accounts' => [['id', 'name', 'status', 'requests', 'downloaded', 'imported', 'duplicates', 'ignored', 'events', 'errors', 'message']]]);
    expect($response->getContent())->not->toContain(CRON_API_KEY)->not->toContain(CRON_TOKEN);
});

test('reports ok=false with HTTP 200 when a run fails', function () {
    cron_account();
    Http::swap(new Factory);
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response('', 403)]);

    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => CRON_TOKEN])
        ->assertOk()
        ->assertJson(['ok' => false])
        ->assertJsonPath('accounts.0.status', 'failed');
});

test('throttles to 6 requests per minute', function () {
    foreach (range(1, 6) as $_) {
        $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => CRON_TOKEN])->assertOk();
    }
    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => CRON_TOKEN])->assertStatus(429);
});

test('throttles token guessing before checking the token', function () {
    foreach (range(1, 6) as $i) {
        $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => 'chute-'.$i])->assertNotFound();
    }
    $this->postJson('/api/cron/sieg-sync', [], ['X-Cron-Token' => 'chute-7'])->assertStatus(429);
});
