<?php

namespace App\Services\Sieg;

use App\Services\Sieg\Exceptions\SiegAuthException;
use App\Services\Sieg\Exceptions\SiegDeadlineException;
use App\Services\Sieg\Exceptions\SiegException;
use App\Services\Sieg\Exceptions\SiegForbiddenException;
use App\Services\Sieg\Exceptions\SiegRateLimitedException;
use App\Services\Sieg\Exceptions\SiegUnavailableException;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Cliente HTTP da API SIEG. Nunca registra nem lança em mensagens: API Key, JWT ou XML.
 */
class SiegClient
{
    private const E_JWT = 'create-jwt';

    private const E_NSU = 'consultar-nsu';

    private const E_NSU_LOTE = 'baixar-xml-nsu-lote';

    private const E_BAIXAR = 'baixar-xmls';

    private const E_CONTAR = 'contar-xmls';

    private const DATE_KEYS = ['DataUploadInicio', 'DataUploadFim', 'DataEmissaoInicio', 'DataEmissaoFim'];

    public function __construct(
        private readonly SiegRateLimiter $limiter,
        private readonly SiegPayloadDecoder $decoder,
    ) {}

    /**
     * @return array{primeiro:int, ultimo:int}
     */
    public function consultarNsu(SiegConnection $conn, ?CarbonInterface $deadline = null): array
    {
        $r = $this->call($conn, self::E_NSU, 'GET', '/api/v1/consultar-nsu', null, $deadline);
        if ($r['status'] === 404) {
            return ['primeiro' => 0, 'ultimo' => 0];
        }

        $body = $this->unwrap($r['body']);
        $primeiro = $this->findKey($body, 'PrimeiroNsu');
        $ultimo = $this->findKey($body, 'UltimoNsu');
        if (! is_numeric($primeiro) || ! is_numeric($ultimo)) {
            throw new SiegException('Resposta de consultar-nsu sem PrimeiroNsu/UltimoNsu.');
        }

        return ['primeiro' => (int) $primeiro, 'ultimo' => (int) $ultimo];
    }

    /**
     * @param  list<int>  $nsus
     */
    public function baixarNsuLote(SiegConnection $conn, array $nsus, ?CarbonInterface $deadline = null): SiegDownloadResult
    {
        if ($nsus === [] || count($nsus) > 50) {
            throw new InvalidArgumentException('O lote de NSUs deve ter de 1 a 50 itens.');
        }

        $r = $this->call($conn, self::E_NSU_LOTE, 'POST', '/api/v1/baixar-xml/nsu/lote', ['NumerosNsu' => array_map('intval', array_values($nsus))], $deadline);

        return $this->toResult($r);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function baixarXmls(SiegConnection $conn, array $filtros, ?CarbonInterface $deadline = null): SiegDownloadResult
    {
        $filtros = $this->normalizeFiltros($filtros);
        if (isset($filtros['Take']) && (int) $filtros['Take'] > 50) {
            throw new InvalidArgumentException('Take deve ser no máximo 50.');
        }

        return $this->toResult($this->call($conn, self::E_BAIXAR, 'POST', '/api/v1/baixar-xmls', $filtros, $deadline));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, int>
     */
    public function contarXmls(SiegConnection $conn, array $filtros, ?CarbonInterface $deadline = null): array
    {
        $r = $this->call($conn, self::E_CONTAR, 'POST', '/api/v1/contar-xmls', $this->normalizeFiltros($filtros), $deadline);
        if ($r['status'] === 404 || ! is_array($r['body'])) {
            return [];
        }

        $counts = [];
        foreach ($this->unwrap($r['body']) as $key => $value) {
            if (is_numeric($value)) {
                $counts[(string) $key] = (int) $value;
            }
        }

        return $counts;
    }

    /**
     * Diagnóstico: uma chamada real a cada endpoint, devolvendo só a ESTRUTURA das respostas.
     *
     * @return array<string, array<string, mixed>>
     */
    public function probe(SiegConnection $conn): array
    {
        $out = [];

        try {
            $response = $this->requestJwt($conn, null);
            $out[self::E_JWT] = ['status' => $response->status(), 'structure' => $this->describe($this->parseBody($response))];
            if ($response->successful()) {
                $this->cacheJwt($conn, $this->extractJwt($this->parseBody($response)));
            }
        } catch (Throwable $e) {
            $out[self::E_JWT] = $this->failure($e);
        }

        try {
            $r = $this->call($conn, self::E_NSU, 'GET', '/api/v1/consultar-nsu', null, null);
            $out[self::E_NSU] = ['status' => $r['status'], 'structure' => $this->describe($r['body'])];
        } catch (Throwable $e) {
            $out[self::E_NSU] = $this->failure($e);
        }

        try {
            $filtros = $this->normalizeFiltros([
                'TipoXml' => 1, 'Take' => 1, 'Skip' => 0,
                'DataUploadInicio' => now()->subDays(2), 'DataUploadFim' => now(),
            ]);
            $r = $this->call($conn, self::E_BAIXAR, 'POST', '/api/v1/baixar-xmls', $filtros, null);
            $out[self::E_BAIXAR] = ['status' => $r['status'], 'structure' => $this->describe($r['body'])];
        } catch (Throwable $e) {
            $out[self::E_BAIXAR] = $this->failure($e);
        }

        return $out;
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array{status:int, body:mixed}  $r
     */
    private function toResult(array $r): SiegDownloadResult
    {
        if ($r['status'] === 404) {
            return new SiegDownloadResult([], true);
        }

        return new SiegDownloadResult($this->decoder->decode($r['body']), false);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function normalizeFiltros(array $filtros): array
    {
        foreach (self::DATE_KEYS as $key) {
            if (($filtros[$key] ?? null) instanceof \DateTimeInterface) {
                $filtros[$key] = $filtros[$key]->format('Y-m-d\TH:i:s');
            }
        }

        return $filtros;
    }

    /**
     * Chamada protegida: JWT, renovação única em 401, mapeamento de status.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{status:int, body:mixed}
     */
    private function call(SiegConnection $conn, string $endpoint, string $method, string $path, ?array $payload, ?CarbonInterface $deadline): array
    {
        $refreshed = false;

        while (true) {
            $jwt = $this->jwt($conn, $deadline);

            $response = $this->execute($conn, $endpoint, function () use ($conn, $jwt, $method, $path, $payload, $deadline) {
                $http = $this->http($conn, $deadline)->withToken($jwt);

                return $method === 'GET' ? $http->get($path) : $http->post($path, $payload ?? []);
            }, $deadline);

            $status = $response->status();

            if ($status === 401) {
                if ($refreshed) {
                    throw new SiegAuthException('SIEG recusou a credencial (401) mesmo após renovar o JWT.', 401);
                }
                Cache::forget($this->jwtCacheKey($conn));
                $refreshed = true;

                continue;
            }
            if ($status === 403) {
                throw new SiegForbiddenException("SIEG negou acesso a '$endpoint' (403): recurso não liberado para a conta.", 403);
            }
            if ($status === 404) {
                return ['status' => 404, 'body' => null];
            }
            if ($status === 400) {
                throw new SiegException("SIEG recusou a requisição a '$endpoint' (400): ".$this->mensagens($response), 400);
            }
            if ($response->successful()) {
                return ['status' => $status, 'body' => $this->parseBody($response)];
            }

            throw new SiegException("Resposta inesperada de '$endpoint' (HTTP $status).", $status);
        }
    }

    /**
     * Envia respeitando o limitador e aplica backoff em 429 / 5xx / falha de conexão.
     *
     * @param  Closure(): Response  $send
     */
    private function execute(SiegConnection $conn, string $endpoint, Closure $send, ?CarbonInterface $deadline): Response
    {
        $attempt = 0;
        $maxRetries = (int) config('sieg.backoff.max_retries');

        while (true) {
            $this->limiter->acquire($conn->limiterKey, $endpoint, $deadline);

            $started = hrtime(true);
            $response = null;
            try {
                $response = $send();
                $status = $response->status();
            } catch (ConnectionException) {
                $status = 0;
            }

            $this->log($endpoint, $status, $started, $conn);

            // Timeout provocado pelo prazo da execução (timeout = restante + 3 s): não é falha da SIEG.
            if ($status === 0 && $deadline !== null && now()->greaterThanOrEqualTo($deadline)) {
                throw new SiegDeadlineException("Prazo da execução esgotado durante '$endpoint'.");
            }

            if ($status !== 429 && $status !== 0 && $status < 500) {
                return $response;
            }

            $retryAfter = $response?->header('Retry-After');
            $retryAfter = is_string($retryAfter) && ctype_digit(trim($retryAfter)) ? (int) trim($retryAfter) : null;

            $attempt++;
            if ($attempt > $maxRetries) {
                if ($status === 429) {
                    throw new SiegRateLimitedException("SIEG manteve 429 em '$endpoint' após $maxRetries tentativas.", $retryAfter);
                }
                throw new SiegUnavailableException("SIEG indisponível em '$endpoint' (".($status === 0 ? 'falha de conexão/timeout' : "HTTP $status").") após $maxRetries tentativas.", $status ?: null);
            }

            $wait = $this->limiter->penalize($conn->limiterKey, $endpoint, $retryAfter, $attempt);
            if ($deadline !== null && now()->addSeconds($wait)->greaterThan($deadline)) {
                throw new SiegDeadlineException("Aguardar {$wait}s em '$endpoint' ultrapassaria o prazo da execução.");
            }
        }
    }

    private function http(SiegConnection $conn, ?CarbonInterface $deadline = null): PendingRequest
    {
        $timeout = (int) config('sieg.timeout');
        $connectTimeout = (int) config('sieg.connect_timeout');
        if ($deadline !== null) {
            // Com prazo, nunca esperamos a SIEG além dele (+3 s de folga para a resposta já em curso).
            $bound = max(0, (int) ceil(now()->diffInSeconds($deadline, false))) + 3;
            $timeout = max(1, min($timeout, $bound));
            $connectTimeout = max(1, min($connectTimeout, $bound));
        }

        return Http::baseUrl((string) config('sieg.base_url'))
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withHeaders([
                'X-API-Key' => $conn->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    private function jwtCacheKey(SiegConnection $conn): string
    {
        return 'sieg:jwt:'.$conn->limiterKey;
    }

    private function jwt(SiegConnection $conn, ?CarbonInterface $deadline): string
    {
        $cached = Cache::get($this->jwtCacheKey($conn));
        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (DecryptException) {
                Cache::forget($this->jwtCacheKey($conn));
            }
        }

        $response = $this->requestJwt($conn, $deadline);
        $status = $response->status();
        if ($status === 401 || $status === 403) {
            throw new SiegAuthException("SIEG recusou a API Key ao criar o JWT ($status).", $status);
        }
        if ($status === 400) {
            throw new SiegException('SIEG recusou a criação do JWT (400): '.$this->mensagens($response), 400);
        }
        if (! $response->successful()) {
            throw new SiegException("Falha ao criar o JWT (HTTP $status).", $status);
        }

        $jwt = $this->extractJwt($this->parseBody($response));
        $this->cacheJwt($conn, $jwt);

        return $jwt;
    }

    private function requestJwt(SiegConnection $conn, ?CarbonInterface $deadline): Response
    {
        return $this->execute($conn, self::E_JWT, fn () => $this->http($conn, $deadline)
            ->withHeaders(['X-Access-Mode' => 'direct'])
            ->post('/api/v1/create-jwt'), $deadline);
    }

    private function cacheJwt(SiegConnection $conn, string $jwt): void
    {
        Cache::put(
            $this->jwtCacheKey($conn),
            Crypt::encryptString($jwt),
            now()->addMinutes((int) config('sieg.jwt_ttl_minutes')),
        );
    }

    private function extractJwt(mixed $body): string
    {
        if (is_string($body)) {
            $token = trim($body, " \t\r\n\"'");
            if ($token !== '') {
                return $token;
            }
        }

        if (is_array($body)) {
            foreach (['token', 'jwt', 'accesstoken', 'access_token'] as $name) {
                $value = $this->findKey($body, $name, false);
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
            $data = $this->findKey($body, 'data', false);
            if (is_string($data) || is_array($data)) {
                return $this->extractJwt($data);
            }
        }

        throw new SiegAuthException('Resposta de create-jwt sem token reconhecível.');
    }

    private function parseBody(Response $response): mixed
    {
        $raw = $response->body();
        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($json) || is_string($json))) {
            return $json;
        }

        return $raw;
    }

    /** Se a resposta vier embrulhada em {"data": {...}}, desembrulha. */
    private function unwrap(mixed $body): mixed
    {
        if (is_array($body) && ! array_is_list($body) && count($body) === 1) {
            $only = reset($body);
            if (is_array($only) && strtolower((string) array_key_first($body)) === 'data') {
                return $only;
            }
        }

        return $body;
    }

    /** Busca chave sem diferenciar maiúsculas; com $nested, entra em "data". */
    private function findKey(mixed $body, string $name, bool $nested = true): mixed
    {
        if (! is_array($body)) {
            return null;
        }
        foreach ($body as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return $value;
            }
        }
        if ($nested) {
            $data = $this->findKey($body, 'data', false);
            if (is_array($data)) {
                return $this->findKey($data, $name, false);
            }
        }

        return null;
    }

    private function mensagens(Response $response): string
    {
        $body = $this->parseBody($response);
        $msgs = is_array($body) ? $this->findKey($body, 'Mensagens', false) : null;
        $text = is_array($msgs) ? implode('; ', array_map(fn ($m) => is_scalar($m) ? (string) $m : json_encode($m), $msgs)) : (is_string($msgs) ? $msgs : 'sem mensagens');

        return mb_substr($text, 0, 500);
    }

    private function log(string $endpoint, int $status, int $startedNs, SiegConnection $conn): void
    {
        $durationMs = (int) round((hrtime(true) - $startedNs) / 1e6);
        // Anomalias (falha, 429, 5xx, lentidão > 10 s) sobem para warning: visíveis com LOG_LEVEL=warning.
        $anomalous = $status === 0 || $status === 429 || $status >= 500 || $durationMs > 10_000;

        Log::channel((string) config('logging.default'))->log($anomalous ? 'warning' : 'info', 'sieg.http', [
            'endpoint' => $endpoint,
            'status' => $status,
            'duration_ms' => $durationMs,
            'limiterKey' => $conn->limiterKey,
        ]);
    }

    /** @return array<string, mixed> */
    private function failure(Throwable $e): array
    {
        return ['error' => class_basename($e), 'status' => $e instanceof SiegException ? $e->httpStatus : null];
    }

    /**
     * Descreve a estrutura (nunca valores).
     *
     * @return array<string, mixed>
     */
    private function describe(mixed $value, int $depth = 0): array
    {
        if (is_string($value)) {
            $trimmed = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $value));
            $decoded = base64_decode((string) preg_replace('/\s+/', '', $value), true);
            $decoded = $decoded === false ? '' : ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $decoded));

            return [
                'type' => 'string',
                'length' => strlen($value),
                'is_xml' => str_starts_with($trimmed, '<'),
                'base64_zip' => str_starts_with($decoded, 'PK'),
                'base64_xml' => str_starts_with($decoded, '<'),
            ];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'type' => 'list',
                    'length' => count($value),
                    'items' => $depth < 3 && $value !== [] ? $this->describe($value[0], $depth + 1) : null,
                ];
            }
            $keys = [];
            foreach ($value as $key => $item) {
                $keys[(string) $key] = $depth < 3 ? $this->describe($item, $depth + 1) : ['type' => get_debug_type($item)];
            }

            return ['type' => 'object', 'keys' => $keys];
        }

        return ['type' => get_debug_type($value)];
    }
}
