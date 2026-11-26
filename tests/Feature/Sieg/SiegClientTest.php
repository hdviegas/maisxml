<?php

use App\Services\Sieg\Exceptions\SiegAuthException;
use App\Services\Sieg\Exceptions\SiegDeadlineException;
use App\Services\Sieg\Exceptions\SiegException;
use App\Services\Sieg\Exceptions\SiegForbiddenException;
use App\Services\Sieg\Exceptions\SiegRateLimitedException;
use App\Services\Sieg\Exceptions\SiegUnavailableException;
use App\Services\Sieg\SiegClient;
use App\Services\Sieg\SiegConnection;
use App\Services\Sieg\SiegRateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Monolog\Handler\TestHandler;

const SIEGC_KEY = 'super-secret-api-key-123';
const SIEGC_JWT = 'jwt.secret.token';

function sieg_xml_zip_b64(): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'tc');
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('n.xml', '<?xml version="1.0"?><nfeProc><secretxml/></nfeProc>');
    $zip->close();
    $b = base64_encode(file_get_contents($tmp));
    unlink($tmp);

    return $b;
}

beforeEach(function () {
    Cache::flush();
    $this->travelTo(now()->startOfSecond());
    Sleep::fake(syncWithCarbon: true);
    config([
        'sieg.base_url' => 'https://api.sieg.com',
        'sieg.rate_limits.baixar-xmls' => 2,
        'sieg.rate_limit_safety' => 0.9,
        'sieg.backoff.base_seconds' => 60,
        'sieg.backoff.max_seconds' => 900,
        'sieg.backoff.max_retries' => 3,
        'logging.channels.sieg_test' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        'logging.default' => 'sieg_test',
    ]);
    $this->conn = new SiegConnection(SIEGC_KEY);
    $this->client = app(SiegClient::class);
});

function sieg_jwt_ok()
{
    return Http::response(SIEGC_JWT, 200);
}

it('creates the JWT with direct mode header, caches it encrypted and sends it on protected calls', function () {
    Http::fake([
        '*/create-jwt' => sieg_jwt_ok(),
        '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 10, 'ultimoNSU' => 99]),
    ]);

    expect($this->client->consultarNsu($this->conn))->toBe(['primeiro' => 10, 'ultimo' => 99]);
    $this->client->consultarNsu($this->conn);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/create-jwt')
        && $r->method() === 'POST'
        && $r->header('X-Access-Mode') === ['direct']
        && $r->header('X-API-Key') === [SIEGC_KEY]);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/consultar-nsu')
        && $r->method() === 'GET'
        && $r->header('Authorization') === ['Bearer '.SIEGC_JWT]
        && $r->header('X-API-Key') === [SIEGC_KEY]
        && $r->header('Accept') === ['application/json']);
    Http::assertSentCount(3); // 1 jwt + 2 consultas

    $cached = Cache::get('sieg:jwt:'.$this->conn->limiterKey);
    expect($cached)->not->toBe(SIEGC_JWT)->and(Crypt::decryptString($cached))->toBe(SIEGC_JWT);
});

it('accepts JWT in json shapes', function (mixed $body) {
    Http::fake([
        '*/create-jwt' => Http::response($body),
        '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 1, 'UltimoNsu' => 2]),
    ]);
    $this->client->consultarNsu($this->conn);
    Http::assertSent(fn (Request $r) => $r->header('Authorization') === ['Bearer '.SIEGC_JWT]);
})->with([
    'accessToken' => [['accessToken' => SIEGC_JWT]],
    'Token' => [['Token' => SIEGC_JWT]],
    'nested data' => [['data' => ['jwt' => SIEGC_JWT]]],
    'quoted string' => ['"'.SIEGC_JWT.'"'],
]);

it('refreshes the JWT once on 401 then succeeds', function () {
    Http::fake([
        '*/create-jwt' => Http::sequence()->push('jwt1')->push('jwt2'),
        '*/consultar-nsu' => Http::sequence()->push('', 401)->push(['PrimeiroNsu' => 1, 'UltimoNsu' => 2]),
    ]);

    expect($this->client->consultarNsu($this->conn)['ultimo'])->toBe(2);
    Http::assertSent(fn (Request $r) => $r->header('Authorization') === ['Bearer jwt2']);
});

it('throws auth exception on second 401', function () {
    Http::fake([
        '*/create-jwt' => sieg_jwt_ok(),
        '*/consultar-nsu' => Http::response('', 401),
    ]);
    expect(fn () => $this->client->consultarNsu($this->conn))->toThrow(SiegAuthException::class);
});

it('maps 404 to notFound', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response('', 404)]);
    $r = $this->client->baixarXmls($this->conn, ['TipoXml' => 1, 'Take' => 50]);
    expect($r->notFound)->toBeTrue()->and($r->documents)->toBe([]);
});

it('decodes a 200 download and formats carbon dates and PascalCase body', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response(['Arquivo' => sieg_xml_zip_b64()])]);
    $r = $this->client->baixarXmls($this->conn, ['TipoXml' => 1, 'Take' => 50, 'DataUploadInicio' => now()->subDay()]);

    expect($r->notFound)->toBeFalse()->and($r->documents)->toHaveCount(1);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/baixar-xmls')
        && $r['DataUploadInicio'] === now()->subDay()->format('Y-m-d\TH:i:s') && $r['Take'] === 50);
});

it('validates Take and NSU batch size', function () {
    Http::fake();
    expect(fn () => $this->client->baixarXmls($this->conn, ['Take' => 51]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->client->baixarNsuLote($this->conn, range(1, 51)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->client->baixarNsuLote($this->conn, []))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('posts NSU batch body', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xml/nsu/lote' => Http::response(base64_encode('<a/>'))]);
    $r = $this->client->baixarNsuLote($this->conn, [5, 6]);
    expect($r->documents)->toHaveCount(1);
    Http::assertSent(fn (Request $q) => str_ends_with($q->url(), '/baixar-xml/nsu/lote') && $q['NumerosNsu'] === [5, 6]);
});

it('counts xmls', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/contar-xmls' => Http::response(['NFe' => 3, 'CTe' => '4', 'x' => 'a'])]);
    expect($this->client->contarXmls($this->conn, ['DataEmissaoInicio' => now()]))->toBe(['NFe' => 3, 'CTe' => 4]);
});

it('waits Retry-After on 429 then succeeds', function () {
    Http::fake([
        '*/create-jwt' => sieg_jwt_ok(),
        '*/baixar-xmls' => Http::sequence()->push('', 429, ['Retry-After' => '45'])->push(['Arquivo' => sieg_xml_zip_b64()]),
    ]);
    $start = now();
    $r = $this->client->baixarXmls($this->conn, ['TipoXml' => 1, 'Take' => 50]);

    expect($r->documents)->toHaveCount(1)
        ->and(now()->diffInSeconds($start, true))->toBeGreaterThanOrEqual(45);
    Sleep::assertSleptTimes(1);
});

it('throws rate limited after max retries', function () {
    config(['sieg.backoff.max_retries' => 2, 'sieg.rate_limits.baixar-xmls' => 1000]);
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response('', 429)]);

    try {
        $this->client->baixarXmls($this->conn, ['TipoXml' => 1, 'Take' => 50]);
        $this->fail('expected exception');
    } catch (SiegRateLimitedException $e) {
        expect($e->retryAfterSeconds)->toBeNull();
    }
    Http::assertSentCount(1 + 3);
});

it('throws deadline exception without sending when waiting would exceed it', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response('', 404)]);
    $this->client->baixarXmls($this->conn, ['TipoXml' => 1]);
    Http::assertSentCount(2);

    expect(fn () => $this->client->baixarXmls($this->conn, ['TipoXml' => 1], now()->addSeconds(5)))
        ->toThrow(SiegDeadlineException::class);
    Http::assertSentCount(2);
});

it('throws deadline exception when backoff does not fit', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response('', 429, ['Retry-After' => '120'])]);
    expect(fn () => $this->client->baixarXmls($this->conn, ['TipoXml' => 1], now()->addSeconds(60)))
        ->toThrow(SiegDeadlineException::class);
});

it('spaces two baixar-xmls calls by ~33.3s at 2/min with 0.9 safety', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response('', 404)]);
    $this->client->baixarXmls($this->conn, ['TipoXml' => 1]);
    $start = now();
    $this->client->baixarXmls($this->conn, ['TipoXml' => 1]);

    expect(now()->diffInMilliseconds($start, true))->toBeBetween(33300, 33400);
});

it('throws forbidden on 403 without retry', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xml/nsu/lote' => Http::response('', 403)]);
    expect(fn () => $this->client->baixarNsuLote($this->conn, [1]))->toThrow(SiegForbiddenException::class);
    Http::assertSentCount(2);
});

it('retries 503 then succeeds', function () {
    Http::fake([
        '*/create-jwt' => sieg_jwt_ok(),
        '*/consultar-nsu' => Http::sequence()->push('', 503)->push(['PrimeiroNsu' => 1, 'UltimoNsu' => 2]),
    ]);
    expect($this->client->consultarNsu($this->conn)['primeiro'])->toBe(1);
    Sleep::assertSleptTimes(1);
});

it('throws unavailable after exhausting retries on 5xx and connection errors', function () {
    config(['sieg.rate_limits.consultar-nsu' => 1000]);
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/consultar-nsu' => fn () => throw new ConnectionException('boom')]);
    expect(fn () => $this->client->consultarNsu($this->conn))->toThrow(SiegUnavailableException::class);
});

it('maps 400 to SiegException with truncated Mensagens', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response(['Mensagens' => ['Filtro inválido', str_repeat('z', 800)]], 400)]);
    try {
        $this->client->baixarXmls($this->conn, ['TipoXml' => 1]);
        $this->fail('expected');
    } catch (SiegException $e) {
        expect($e->getMessage())->toContain('Filtro inválido')->and(strlen($e->getMessage()))->toBeLessThan(700);
    }
});

it('probe returns only structure and never secrets or xml content', function () {
    Http::fake([
        '*/create-jwt' => Http::response(['token' => SIEGC_JWT]),
        '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 777, 'UltimoNsu' => 888]),
        '*/baixar-xmls' => Http::response(['Status' => 'OK', 'Arquivo' => sieg_xml_zip_b64()]),
    ]);
    $result = $this->client->probe($this->conn);
    $json = json_encode($result);

    expect($result)->toHaveKeys(['create-jwt', 'consultar-nsu', 'baixar-xmls'])
        ->and($result['baixar-xmls']['structure']['keys']['Arquivo']['base64_zip'])->toBeTrue()
        ->and($result['create-jwt']['status'])->toBe(200)
        ->and($json)->not->toContain(SIEGC_KEY)->not->toContain(SIEGC_JWT)
        ->not->toContain('secretxml')->not->toContain('777')->not->toContain('OK');
});

it('probe records failures per call by class and status', function () {
    Http::fake(['*/create-jwt' => Http::response('', 401)]);
    $result = $this->client->probe($this->conn);
    expect($result['create-jwt']['status'])->toBe(401);
    expect($result['consultar-nsu'])->toHaveKey('error');
});

it('never logs the api key or jwt', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/baixar-xmls' => Http::response(['Arquivo' => sieg_xml_zip_b64()])]);
    $this->client->baixarXmls($this->conn, ['TipoXml' => 1]);

    $handler = Log::channel('sieg_test')->getLogger()->getHandlers()[0];
    $records = $handler->getRecords();
    expect($records)->not->toBeEmpty();
    $dump = json_encode(array_map(fn ($r) => [$r['message'], $r['context']], $records));
    expect($dump)->not->toContain(SIEGC_KEY)->not->toContain(SIEGC_JWT)->not->toContain('secretxml')
        ->toContain($this->conn->limiterKey);
});

function sieg_http_levels(): array
{
    $handler = Log::channel('sieg_test')->getLogger()->getHandlers()[0];

    return collect($handler->getRecords())->filter(fn ($r) => $r['message'] === 'sieg.http')
        ->map(fn ($r) => $r['level_name'])->values()->all();
}

it('clamps the http timeouts to the time left until the deadline', function () {
    config(['sieg.rate_limits.consultar-nsu' => 6000]);
    $seen = [];
    Http::fake([
        '*/create-jwt' => sieg_jwt_ok(),
        '*/consultar-nsu' => function (Request $r, array $options) use (&$seen) {
            $seen = [$options['timeout'], $options['connect_timeout']];

            return Http::response(['PrimeiroNsu' => 1, 'UltimoNsu' => 2]);
        },
    ]);

    $this->client->consultarNsu($this->conn, now()->addSeconds(5));
    expect($seen)->toBe([8, 8]);

    $this->client->consultarNsu($this->conn, now()->addSeconds(1));
    expect($seen)->toBe([4, 4]);

    $this->client->consultarNsu($this->conn, now()->addMinutes(10));
    expect($seen)->toBe([60, 10]);

    $this->client->consultarNsu($this->conn);
    expect($seen)->toBe([60, 10]);
});

it('turns a connection timeout caused by the deadline into SiegDeadlineException without backoff', function () {
    Http::fake([
        '*/create-jwt' => sieg_jwt_ok(),
        '*/consultar-nsu' => function () {
            $this->travel(10)->seconds(); // passa do prazo enquanto "espera" a SIEG

            throw new ConnectionException('cURL error 28: timeout');
        },
    ]);

    expect(fn () => $this->client->consultarNsu($this->conn, now()->addSeconds(5)))
        ->toThrow(SiegDeadlineException::class);
    Sleep::assertNeverSlept();
    expect(app(SiegRateLimiter::class)->penalize($this->conn->limiterKey, 'consultar-nsu', null, 1))->toBeInt();
});

it('logs http anomalies at warning and healthy calls at info', function () {
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/consultar-nsu' => Http::response(['PrimeiroNsu' => 1, 'UltimoNsu' => 2])]);
    $this->client->consultarNsu($this->conn);
    expect(sieg_http_levels())->toBe(['INFO', 'INFO']);

});

it('logs a 5xx response at warning level', function () {
    config(['sieg.backoff.max_retries' => 0]);
    Http::fake(['*/create-jwt' => sieg_jwt_ok(), '*/consultar-nsu' => Http::response('', 503)]);
    expect(fn () => $this->client->consultarNsu($this->conn))->toThrow(SiegUnavailableException::class);
    expect(sieg_http_levels())->toBe(['INFO', 'WARNING']);
});
