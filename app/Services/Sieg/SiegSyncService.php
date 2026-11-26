<?php

namespace App\Services\Sieg;

use App\Enums\OnDuplicateImport;
use App\Models\Branch;
use App\Models\SiegAccount;
use App\Models\SiegSyncCursor;
use App\Models\SiegSyncRun;
use App\Services\Nfe\NfeEventImporter;
use App\Services\Nfe\NfeImportService;
use App\Services\Sieg\Exceptions\SiegAuthException;
use App\Services\Sieg\Exceptions\SiegDeadlineException;
use App\Services\Sieg\Exceptions\SiegForbiddenException;
use App\Services\Sieg\Exceptions\SiegRateLimitedException;
use App\Services\Sieg\Exceptions\SiegUnavailableException;
use App\Support\Tenant\Cnpj;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orquestra uma rodada de sincronização de uma conta SIEG (modos "nsu", "periodo" e "compras").
 * Nunca registra API Key, JWT nem XML.
 */
class SiegSyncService
{
    private const CAUGHT_UP_MARGIN_MINUTES = 5;

    /** Há mais trabalho pendente quando o prazo estoura. */
    private bool $deadlineHit = false;

    /** @var list<string> */
    private array $notes = [];

    public function __construct(
        private readonly SiegClient $client,
        private readonly NfeImportService $nfeImporter,
        private readonly NfeEventImporter $eventImporter,
    ) {}

    public function sync(SiegAccount $account, CarbonInterface $deadline, bool $force = false): SiegSyncRun
    {
        if (! $account->enabled) {
            return $this->skipped($account, 'Conta desabilitada.');
        }
        if ($account->isPaused() && ! $force) {
            return $this->skipped($account, 'Conta pausada até '.$account->paused_until?->format('Y-m-d H:i').'.');
        }

        $ttl = max(1, (int) now()->diffInSeconds($deadline, false)) + 60;
        $lock = Cache::lock("sieg:sync:account:{$account->id}", $ttl);
        if (! $lock->get()) {
            return $this->skipped($account, 'execução em andamento');
        }

        try {
            return $this->run($account, $deadline);
        } finally {
            $lock->release();
        }
    }

    private function skipped(SiegAccount $account, string $message): SiegSyncRun
    {
        $run = SiegSyncRun::query()->create([
            'sieg_account_id' => $account->id,
            'mode' => $account->mode,
            'status' => 'skipped',
            'started_at' => now(),
        ] + array_fill_keys(SiegSyncRun::COUNTERS, 0));
        $run->finish('skipped', $message);

        return $run;
    }

    private function run(SiegAccount $account, CarbonInterface $deadline): SiegSyncRun
    {
        $this->deadlineHit = false;
        $this->notes = [];

        $this->abortStaleRuns($account);

        $run = SiegSyncRun::query()->create([
            'sieg_account_id' => $account->id,
            'mode' => $account->mode,
            'status' => 'running',
            'started_at' => now(),
        ] + array_fill_keys(SiegSyncRun::COUNTERS, 0));
        $account->last_run_at = now();
        $account->save();

        $status = 'ok';
        $message = null;

        try {
            $conn = $account->connection();
            match ($account->mode) {
                'nsu' => $this->syncNsu($account, $conn, $run, $deadline),
                'periodo', SiegAccount::MODE_COMPRAS => $this->syncPeriodo($account, $conn, $run, $deadline),
                default => throw new InvalidArgumentException('unknown_mode'),
            };
            $status = $this->deadlineHit ? 'partial' : 'ok';
        } catch (SiegDeadlineException) {
            $status = 'partial';
        } catch (SiegForbiddenException) {
            $status = 'failed';
            $message = 'SIEG recusou (403). Se for o modo NSU, use --mode=periodo no sieg:account.';
        } catch (SiegAuthException) {
            $status = 'failed';
            $message = 'API Key SIEG inválida ou expirada (401)';
        } catch (SiegRateLimitedException|SiegUnavailableException $e) {
            $status = 'failed';
            $message = class_basename($e).($e->httpStatus ? " (HTTP {$e->httpStatus})" : '');
        } catch (Throwable $e) {
            Log::error('sieg.sync.failed', ['account_id' => $account->id, 'exception' => $e]);
            $status = 'failed';
            $message = $e instanceof RuntimeException && $e->getMessage() === 'storage_failed'
                ? 'Falha ao gravar o XML no armazenamento (storage_failed)'
                : class_basename($e);
        }

        $notes = implode(' ', $this->notes);
        if ($status === 'failed') {
            $account->recordFailure((string) $message);
            $finalMessage = trim($message.' '.$notes);
        } else {
            $account->recordSuccess();
            $finalMessage = $notes !== '' ? $notes : null;
        }

        $run->finish($status, $finalMessage);

        Log::info('sieg.sync', [
            'account_id' => $account->id,
            'mode' => $account->mode,
            'status' => $status,
            'requests' => $run->requests,
            'downloaded' => $run->downloaded,
            'imported' => $run->imported,
            'duplicates' => $run->duplicates,
            'ignored' => $run->ignored,
            'events' => $run->events,
            'errors' => $run->errors,
            'duration_ms' => (int) $run->started_at->diffInMilliseconds($run->finished_at),
        ]);

        return $run;
    }

    /** Linhas 'running' de processos que morreram (timeout do PHP/host) nunca seriam fechadas. */
    private function abortStaleRuns(SiegAccount $account): void
    {
        $maxRuntime = max((int) config('sieg.cron.max_runtime_seconds'), (int) config('sieg.sync.max_runtime_seconds'));

        SiegSyncRun::query()
            ->where('sieg_account_id', $account->id)
            ->where('status', 'running')
            ->where('started_at', '<', now()->subSeconds($maxRuntime + 120))
            ->update([
                'status' => 'aborted',
                'finished_at' => now(),
                'message' => 'interrompida (processo encerrado)',
            ]);
    }

    // ------------------------------------------------------------------ NSU

    private function syncNsu(SiegAccount $account, SiegConnection $conn, SiegSyncRun $run, CarbonInterface $deadline): void
    {
        $range = $this->nsuRange($account, $conn, $run, $deadline);
        $primeiro = $range['primeiro'];
        $ultimo = $range['ultimo'];

        if ($primeiro === 0 && $ultimo === 0) {
            return;
        }

        $cursor = $account->last_nsu ?? ($primeiro - 1);
        if ($cursor < $primeiro - 1) {
            $this->notes[] = sprintf('lacuna de NSU: %d..%d indisponíveis (mais de 4 meses)', $cursor + 1, $primeiro - 1);
            $cursor = $primeiro - 1;
        }

        $batchSize = max(1, min(50, (int) config('sieg.sync.nsu_batch')));

        $lastBatchSeconds = 0.0;

        while ($cursor < $ultimo) {
            // Não inicia um lote que, pela duração do anterior, não terminaria dentro do prazo.
            $needed = (int) round(max($lastBatchSeconds, 3.0) * 1000);
            if (now()->addMilliseconds($needed)->greaterThan($deadline)) {
                $this->deadlineHit = true;
                break;
            }

            $batchStarted = now();
            $from = $cursor + 1;
            $to = min($cursor + $batchSize, $ultimo);

            $result = $this->client->baixarNsuLote($conn, range($from, $to), $deadline);
            $run->bump('requests');
            $this->route($account, $run, $result->documents);

            $account->last_nsu = $to;
            $account->save();
            $cursor = $to;

            $run->from_nsu ??= $from;
            $run->to_nsu = $to;
            $run->save();

            $lastBatchSeconds = max(0.0, (float) $batchStarted->diffInSeconds(now(), false));
        }
    }

    /**
     * Faixa de NSUs da conta. Reaproveita a última consulta enquanto o cursor não a alcançou,
     * evitando uma chamada (lenta) a consultar-nsu em toda rodada.
     *
     * @return array{primeiro:int, ultimo:int}
     */
    private function nsuRange(SiegAccount $account, SiegConnection $conn, SiegSyncRun $run, CarbonInterface $deadline): array
    {
        $key = "sieg:nsu_range:{$account->id}";
        $cached = Cache::get($key);

        if (is_array($cached) && isset($cached['primeiro'], $cached['ultimo'])
            && ($account->last_nsu ?? ($cached['primeiro'] - 1)) < $cached['ultimo']) {
            return ['primeiro' => (int) $cached['primeiro'], 'ultimo' => (int) $cached['ultimo']];
        }

        $range = $this->client->consultarNsu($conn, $deadline);
        $run->bump('requests');

        $ttl = (int) config('sieg.sync.nsu_range_cache_seconds');
        if ($ttl > 0 && ($range['primeiro'] !== 0 || $range['ultimo'] !== 0)) {
            Cache::put($key, $range, now()->addSeconds($ttl));
        } else {
            Cache::forget($key);
        }

        return $range;
    }

    // -------------------------------------------------------------- período

    private function syncPeriodo(SiegAccount $account, SiegConnection $conn, SiegSyncRun $run, CarbonInterface $deadline): void
    {
        $cursors = [];
        foreach ($this->cursorSpecs($account) as [$cnpj, $role, $tipo]) {
            $cursors[] = SiegSyncCursor::query()->firstOrCreate([
                'sieg_account_id' => $account->id,
                'cnpj' => $cnpj,
                'role' => $role,
                'tipo_xml' => $tipo,
            ]);
        }

        // Rodízio justo: com baixar-xmls a 2/min cabe ~1 consulta por rodada, então começar sempre
        // pelo mesmo cursor deixaria os demais sem vez. Primeiro o mais atrasado (nunca sincronizado
        // antes de todos), depois pelo synced_until mais antigo.
        usort($cursors, fn (SiegSyncCursor $a, SiegSyncCursor $b): int => [$a->synced_until !== null, $a->synced_until?->getTimestamp() ?? 0, $a->id]
            <=> [$b->synced_until !== null, $b->synced_until?->getTimestamp() ?? 0, $b->id]);

        foreach ($cursors as $cursor) {
            $this->syncCursor($account, $conn, $run, $cursor, $deadline, $this->modeSpec($account)['lookback_hours']);

            if ($this->deadlineHit) {
                return;
            }
        }
    }

    /**
     * Definição por modo: escopo dos cursores, papéis, tipos e janela inicial (em horas).
     * `compras` é sempre por filial, só como destinatário (dest) e só NF-e (tipo 1),
     * independente de `periodo_scope`.
     *
     * @return array{branch:bool, roles:list<string>, tipos:list<int>, lookback_hours:int}
     */
    private function modeSpec(SiegAccount $account): array
    {
        if ($account->mode === SiegAccount::MODE_COMPRAS) {
            return [
                'branch' => true,
                'roles' => ['dest'],
                'tipos' => [1],
                'lookback_hours' => max(1, (int) config('sieg.sync.compras_initial_lookback_hours')),
            ];
        }

        return [
            'branch' => config('sieg.sync.periodo_scope') === 'branch',
            'roles' => ['dest', 'emit'],
            'tipos' => array_map('intval', (array) config('sieg.sync.tipos_xml')),
            'lookback_hours' => (int) config('sieg.sync.initial_lookback_days') * 24,
        ];
    }

    /** @return list<array{0:string,1:string,2:int}> */
    private function cursorSpecs(SiegAccount $account): array
    {
        $def = $this->modeSpec($account);
        $tipos = $def['tipos'];
        $specs = [];

        if ($def['branch']) {
            $cnpjs = Branch::query()->where('empresa', $account->organization_id)->pluck('cnpj')
                ->map(fn ($c) => Cnpj::digits((string) $c))->filter()->unique()->values();
            foreach ($cnpjs as $cnpj) {
                foreach ($def['roles'] as $role) {
                    foreach ($tipos as $tipo) {
                        $specs[] = [$cnpj, $role, $tipo];
                    }
                }
            }

            return $specs;
        }

        foreach ($tipos as $tipo) {
            $specs[] = ['*', 'all', $tipo];
        }

        return $specs;
    }

    private function syncCursor(SiegAccount $account, SiegConnection $conn, SiegSyncRun $run, SiegSyncCursor $cursor, CarbonInterface $deadline, int $lookbackHours): void
    {
        $pageSize = max(1, min(50, (int) config('sieg.sync.page_size')));
        $windowHours = max(1, (int) config('sieg.sync.window_hours'));
        $overlapHours = (int) config('sieg.sync.overlap_hours');

        // Dentro de uma rodada, janelas consecutivas seguem a anterior (sem reaplicar a sobreposição).
        $resumeFrom = null;

        while (true) {
            if (now()->greaterThanOrEqualTo($deadline)) {
                $this->deadlineHit = true;

                return;
            }
            $now = now()->startOfSecond();

            if ($cursor->window_start === null || $cursor->window_end === null) {
                if ($resumeFrom !== null) {
                    $start = $resumeFrom;
                } elseif ($cursor->synced_until !== null) {
                    $caughtUp = $cursor->synced_until->greaterThanOrEqualTo($now->copy()->subHours($windowHours));
                    $start = $caughtUp ? $cursor->synced_until->copy()->subHours($overlapHours) : $cursor->synced_until->copy();
                } else {
                    $start = $now->copy()->subHours($lookbackHours);
                }
                $end = $start->copy()->addHours($windowHours);
                if ($end->greaterThan($now)) {
                    $end = $now->copy();
                }
                $cursor->window_start = $start;
                $cursor->window_end = $end;
                $cursor->skip = 0;
                $cursor->save();
            }

            $filtros = [
                'TipoXml' => $cursor->tipo_xml,
                'Take' => $pageSize,
                'Skip' => (int) $cursor->skip,
                'DataUploadInicio' => $cursor->window_start,
                'DataUploadFim' => $cursor->window_end,
                'BaixarEventos' => true,
            ];
            if ($cursor->role === 'dest') {
                $filtros['CnpjDest'] = $cursor->cnpj;
            } elseif ($cursor->role === 'emit') {
                $filtros['CnpjEmit'] = $cursor->cnpj;
            }

            $result = $this->client->baixarXmls($conn, $filtros, $deadline);
            $run->bump('requests');
            $nonEvents = $this->route($account, $run, $result->documents);

            if ($result->notFound || $result->documents === [] || $nonEvents < $pageSize) {
                $windowEnd = $cursor->window_end;
                $cursor->synced_until = $cursor->synced_until !== null && $cursor->synced_until->greaterThan($windowEnd)
                    ? $cursor->synced_until
                    : $windowEnd;
                $cursor->window_start = null;
                $cursor->window_end = null;
                $cursor->skip = 0;
                $cursor->save();
                $resumeFrom = $windowEnd;

                if ($windowEnd->greaterThanOrEqualTo(now()->subMinutes(self::CAUGHT_UP_MARGIN_MINUTES))) {
                    return;
                }

                continue;
            }

            $cursor->skip = (int) $cursor->skip + $pageSize;
            $cursor->save();
        }
    }

    // ------------------------------------------------------------- roteamento

    /**
     * @param  list<SiegDocument>  $documents
     * @return int quantidade de documentos que NÃO são eventos (base da paginação)
     */
    private function route(SiegAccount $account, SiegSyncRun $run, array $documents): int
    {
        $nonEvents = 0;

        foreach ($documents as $doc) {
            $run->bump('downloaded');
            $kind = $this->classify($doc->xml);
            if ($kind !== 'event') {
                $nonEvents++;
            }

            try {
                switch ($kind) {
                    case 'event':
                        match ($this->eventImporter->import($account->organization_id, $doc->xml, 'sieg')) {
                            'created' => $run->bump('events'),
                            'duplicate' => $run->bump('duplicates'),
                            default => $run->bump('ignored'),
                        };
                        break;
                    case 'nfe':
                        $outcome = $this->nfeImporter->importForOrganization($account->organization_id, $doc->xml, OnDuplicateImport::Skip, 'sieg');
                        $run->bump($outcome->result === 'skipped' ? 'duplicates' : 'imported');
                        break;
                    case 'invalid':
                        $run->bump('errors');
                        break;
                    default:
                        $run->bump('ignored');
                }
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'xml_not_for_organization') {
                    $run->bump('ignored');
                    Log::warning('sieg.sync.foreign_xml', ['account_id' => $account->id, 'chave' => $this->chave($doc->xml)]);
                } else {
                    $run->bump('errors');
                }
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== 'duplicate_chave_other_org') {
                    throw $e;
                }
                $run->bump('ignored');
                Log::warning('sieg.sync.duplicate_other_org', ['account_id' => $account->id, 'chave' => $this->chave($doc->xml)]);
            }
        }

        return $nonEvents;
    }

    /** @return 'event'|'nfe'|'other'|'invalid' */
    private function classify(string $xml): string
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            if (trim($xml) === '' || ! $dom->loadXML($xml, LIBXML_NONET)) {
                return 'invalid';
            }
            $xpath = new DOMXPath($dom);
            if ($xpath->query("//*[local-name()='infEvento'][*[local-name()='chNFe']]")->length > 0) {
                return 'event';
            }

            // Só infNFe filho de <NFe>: o CT-e também tem infDoc/infNFe (referência às notas transportadas).
            return $xpath->query("//*[local-name()='NFe']/*[local-name()='infNFe']")->length > 0 ? 'nfe' : 'other';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function chave(string $xml): ?string
    {
        return preg_match('/(?<!\d)\d{44}(?!\d)/', $xml, $m) === 1 ? $m[0] : null;
    }
}
