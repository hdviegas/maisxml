<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\SiegAccount;
use App\Models\SiegSyncCursor;
use App\Services\Nfe\NfeImportService;
use App\Services\Sieg\SiegSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

const SYNC_ORG = '11222333000181';
const SYNC_ORG_B2 = '11222333000262';
const SYNC_OTHER = '44555666000199';
const SYNC_SUP = '99888777000166';

function sync_org(string $cnpj = SYNC_ORG, array $extra = []): Organization
{
    $org = Organization::query()->create(['social' => 'Org '.$cnpj, 'fantasia' => 'Org', 'cnpj' => $cnpj]);
    foreach ([$cnpj, ...$extra] as $c) {
        Branch::query()->create(['empresa' => $org->id, 'social' => 'Filial', 'fantasia' => null, 'cnpj' => $c, 'entrada' => true]);
    }

    return $org;
}

function sync_account(Organization $org, array $attrs = []): SiegAccount
{
    return SiegAccount::factory()->create(['organization_id' => $org->id] + $attrs);
}

function sync_chave(string $fill, string $emit = SYNC_ORG): string
{
    return chave_for($fill, $emit);
}

function sync_note(string $fill, string $emit = SYNC_ORG): string
{
    return sample_nfe_xml(sync_chave($fill, $emit), $emit, SYNC_SUP);
}

/** @param list<string> $xmls */
function sync_zip(array $xmls): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'sz');
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach (array_values($xmls) as $i => $xml) {
        $zip->addFromString("d{$i}.xml", $xml);
    }
    $zip->close();
    $b64 = base64_encode((string) file_get_contents($tmp));
    unlink($tmp);

    return ['Arquivo' => $b64];
}

/** @return list<Request> */
function sync_requests(string $suffix): array
{
    return Http::recorded(fn (Request $r) => str_ends_with($r->url(), $suffix))
        ->map(fn ($pair) => $pair[0])->values()->all();
}

beforeEach(function () {
    Cache::flush();
    Storage::fake('local');
    $this->travelTo(now()->startOfSecond());
    Sleep::fake();
    config([
        'sieg.base_url' => 'https://api.sieg.com',
        'sieg.rate_limits' => array_map(fn () => 6000, config('sieg.rate_limits')),
        'sieg.backoff.max_retries' => 1,
        'sieg.sync.page_size' => 2,
        'sieg.sync.nsu_batch' => 2,
        'sieg.sync.tipos_xml' => [1],
        'sieg.sync.periodo_scope' => 'account',
        'sieg.sync.window_hours' => 24,
        'sieg.sync.overlap_hours' => 48,
        'sieg.sync.initial_lookback_days' => 1,
    ]);
    $this->deadline = fn () => now()->addMinutes(10);
    $this->service = fn () => app(SiegSyncService::class);
});

function sync_fake_nsu(int $primeiro, int $ultimo, Closure $lote): void
{
    Http::fake([
        '*/create-jwt' => Http::response('jwt-token', 200),
        '*/consultar-nsu' => Http::response(['PrimeiroNsu' => $primeiro, 'UltimoNsu' => $ultimo]),
        '*/baixar-xml/nsu/lote' => fn (Request $r) => Http::response(sync_zip($lote($r['NumerosNsu']))),
    ]);
}

test('nsu: first run starts at primeiro-1, checkpoints every batch and second run is a no-op', function () {
    $org = sync_org();
    $account = sync_account($org);
    $checkpoints = [];
    sync_fake_nsu(10, 13, function (array $nsus) use ($account, &$checkpoints) {
        $checkpoints[] = $account->fresh()->last_nsu;

        return array_map(fn ($n) => sync_note((string) ($n % 10), SYNC_ORG), $nsus);
    });

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('ok')
        ->and($run->from_nsu)->toBe(10)->and($run->to_nsu)->toBe(13)
        ->and($run->requests)->toBe(3)->and($run->downloaded)->toBe(4)->and($run->imported)->toBe(4)
        ->and($checkpoints)->toBe([null, 11])
        ->and($account->fresh()->last_nsu)->toBe(13)
        ->and($account->fresh()->last_run_at)->not->toBeNull()
        ->and($account->fresh()->last_success_at)->not->toBeNull()
        ->and(FiscalDocument::query()->count())->toBe(4);

    $second = ($this->service)()->sync($account->fresh(), ($this->deadline)());
    expect($second->status)->toBe('ok')->and($second->downloaded)->toBe(0)->and($second->requests)->toBe(1);
    expect(sync_requests('/baixar-xml/nsu/lote'))->toHaveCount(2);
});

test('nsu: both zero means nothing to do', function () {
    $account = sync_account(sync_org());
    sync_fake_nsu(0, 0, fn () => []);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('ok')->and($run->requests)->toBe(1)->and($account->fresh()->last_nsu)->toBeNull();
});

test('nsu: deadline hit mid-way gives partial and the next run resumes', function () {
    $org = sync_org();
    $account = sync_account($org);
    $first = true;
    sync_fake_nsu(10, 13, function (array $nsus) use (&$first) {
        if ($first) {
            $first = false;
            $this->travel(200)->seconds(); // estoura o prazo de 100s depois do 1º lote
        }

        return array_map(fn ($n) => sync_note((string) ($n % 10)), $nsus);
    });

    $run = ($this->service)()->sync($account, now()->addSeconds(100));

    expect($run->status)->toBe('partial')
        ->and($account->fresh()->last_nsu)->toBe(11)
        ->and($run->to_nsu)->toBe(11)
        ->and($account->fresh()->consecutive_failures)->toBe(0)
        ->and($account->fresh()->last_success_at)->not->toBeNull();

    $next = ($this->service)()->sync($account->fresh(), ($this->deadline)());
    expect($next->status)->toBe('ok')->and($next->from_nsu)->toBe(12)->and($account->fresh()->last_nsu)->toBe(13);
});

test('nsu: gap warning when last_nsu is older than primeiro-1', function () {
    $account = sync_account(sync_org(), ['last_nsu' => 5]);
    sync_fake_nsu(10, 11, fn (array $n) => ['<?xml version="1.0"?><cteProc><CTe><infCte Id="C"><x/></infCte></CTe></cteProc>']);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('ok')
        ->and($run->message)->toContain('lacuna de NSU: 6..9 indisponíveis (mais de 4 meses)')
        ->and($run->from_nsu)->toBe(10)
        ->and($account->fresh()->last_nsu)->toBe(11);
});

test('nsu: 403 fails the run, records failure and suggests periodo', function () {
    $account = sync_account(sync_org());
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response('', 403)]);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('failed')
        ->and($run->message)->toContain('403')->toContain('--mode=periodo')
        ->and($account->fresh()->consecutive_failures)->toBe(1)
        ->and($account->fresh()->last_error)->toContain('periodo')
        ->and($run->finished_at)->not->toBeNull();
});

test('401 after renewing the JWT fails the run', function () {
    $account = sync_account(sync_org());
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response('', 401)]);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('failed')->and($run->message)->toContain('401')
        ->and($account->fresh()->consecutive_failures)->toBe(1);
    expect(sync_requests('/create-jwt'))->toHaveCount(2);
});

test('429 and 5xx fail the run with class and status only', function () {
    $account = sync_account(sync_org());
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/consultar-nsu' => Http::response('boom-secret-body', 503)]);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('failed')->and($run->message)->toContain('SiegUnavailableException')->toContain('503')
        ->and($run->message)->not->toContain('boom-secret-body');
});

test('routes events, foreign, duplicate, CT-e and malformed documents', function () {
    $org = sync_org();
    $other = sync_org(SYNC_OTHER);
    $account = sync_account($org);
    config(['sieg.sync.nsu_batch' => 50]);

    $dupXml = sync_note('7');
    app(NfeImportService::class)->importForOrganization($org->id, $dupXml);

    $chave = sync_chave('1');
    $docs = [
        sync_note('1'),
        sample_nfe_event_xml($chave),
        sync_note('3', SYNC_OTHER),
        $dupXml,
        '<?xml version="1.0"?><cteProc><CTe><infCte Id="CTe1"><x/></infCte></CTe></cteProc>',
        '<?xml version="1.0"?><nfeProc><NFe><infNFe Id="NFe1"><unclosed></NFe></nfeProc>',
    ];
    sync_fake_nsu(1, 6, fn () => $docs);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('ok')
        ->and($run->downloaded)->toBe(6)
        ->and($run->imported)->toBe(1)
        ->and($run->events)->toBe(1)
        ->and($run->ignored)->toBe(2)
        ->and($run->duplicates)->toBe(1)
        ->and($run->errors)->toBe(1)
        ->and(FiscalDocument::query()->where('chave', $chave)->sole()->cancelada)->toBeTrue()
        ->and($account->fresh()->last_nsu)->toBe(6);

    // Reenvio do mesmo lote: tudo duplicado/ignorado.
    $account->update(['last_nsu' => null]);
    $again = ($this->service)()->sync($account->fresh(), ($this->deadline)());
    expect($again->imported)->toBe(0)->and($again->events)->toBe(0)->and($again->duplicates)->toBe(3);
});

test('storage failure or unexpected exception aborts without advancing the checkpoint', function (Throwable $error, string $expected) {
    $org = sync_org();
    $account = sync_account($org);
    $cte = '<?xml version="1.0"?><cteProc><CTe><infCte Id="C"><x/></infCte></CTe></cteProc>';
    sync_fake_nsu(10, 13, fn (array $nsus) => $nsus[0] === 10 ? [$cte, $cte.' '] : [sync_note('1'), sync_note('2')]);
    $this->mock(NfeImportService::class)->shouldReceive('importForOrganization')->andThrow($error);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('failed')
        ->and($run->message)->toContain($expected)->not->toContain('segredo-xyz')
        ->and($account->fresh()->last_nsu)->toBe(11)
        ->and($run->to_nsu)->toBe(11)
        ->and($account->fresh()->consecutive_failures)->toBe(1);
})->with([
    'storage_failed' => [fn () => new RuntimeException('storage_failed'), 'storage_failed'],
    'unexpected' => [fn () => new LogicException('segredo-xyz'), 'LogicException'],
]);

// ----------------------------------------------------------------- período

function sync_fake_periodo(Closure $handler): void
{
    Http::fake([
        '*/create-jwt' => Http::response('jwt-token', 200),
        '*/baixar-xmls' => fn (Request $r) => $handler($r->data()) ?? Http::response('', 404),
    ]);
}

test('periodo/account: pages with Skip, completes the window, then rescans the overlap', function () {
    $org = sync_org();
    $account = sync_account($org, ['mode' => 'periodo']);
    $start = now()->subDay();
    $empty = false;
    sync_fake_periodo(function (array $b) use (&$empty) {
        return $empty ? null : match ($b['Skip']) {
            0 => Http::response(sync_zip([sync_note('1'), sync_note('2')])),
            2 => Http::response(sync_zip([sync_note('3')])),
            default => null,
        };
    });

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('ok')->and($run->requests)->toBe(2)->and($run->imported)->toBe(3);
    $reqs = sync_requests('/baixar-xmls');
    expect($reqs)->toHaveCount(2);
    expect($reqs[0]->data())->toBe([
        'TipoXml' => 1, 'Take' => 2, 'Skip' => 0,
        'DataUploadInicio' => $start->format('Y-m-d\TH:i:s'),
        'DataUploadFim' => now()->format('Y-m-d\TH:i:s'),
        'BaixarEventos' => true,
    ]);
    expect($reqs[1]->data()['Skip'])->toBe(2);

    $cursor = SiegSyncCursor::query()->sole();
    expect($cursor->cnpj)->toBe('*')->and($cursor->role)->toBe('all')->and($cursor->tipo_xml)->toBe(1)
        ->and($cursor->synced_until->equalTo(now()))->toBeTrue()
        ->and($cursor->window_start)->toBeNull()->and($cursor->window_end)->toBeNull()->and($cursor->skip)->toBe(0);

    // Uma hora depois: reabre 48h antes do synced_until e avança em janelas consecutivas até agora.
    $syncedUntil = now()->copy();
    $this->travel(1)->hours();
    $empty = true;
    $second = ($this->service)()->sync($account->fresh(), ($this->deadline)());

    expect($second->status)->toBe('ok')->and($second->requests)->toBe(3);
    $w = array_map(fn (Request $r) => [$r['DataUploadInicio'], $r['DataUploadFim']], sync_requests('/baixar-xmls'));
    $f = fn ($d) => $d->format('Y-m-d\TH:i:s');
    expect(array_slice($w, 2))->toBe([
        [$f($syncedUntil->copy()->subHours(48)), $f($syncedUntil->copy()->subHours(24))],
        [$f($syncedUntil->copy()->subHours(24)), $f($syncedUntil)],
        [$f($syncedUntil), $f(now())],
    ]);
});

test('periodo: catch-up opens consecutive windows', function () {
    config(['sieg.sync.initial_lookback_days' => 3]);
    $account = sync_account(sync_org(), ['mode' => 'periodo']);
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/baixar-xmls' => Http::response('', 404)]);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    $f = fn ($d) => $d->format('Y-m-d\TH:i:s');
    $w = array_map(fn (Request $r) => [$r['DataUploadInicio'], $r['DataUploadFim']], sync_requests('/baixar-xmls'));
    expect($run->requests)->toBe(3)->and($w)->toBe([
        [$f(now()->subDays(3)), $f(now()->subDays(2))],
        [$f(now()->subDays(2)), $f(now()->subDays(1))],
        [$f(now()->subDays(1)), $f(now())],
    ]);
    expect(SiegSyncCursor::query()->sole()->synced_until->equalTo(now()))->toBeTrue();
});

test('periodo: a full page of events does not count as a full page of notes', function () {
    $org = sync_org();
    $account = sync_account($org, ['mode' => 'periodo']);
    $note = sync_note('1');
    app(NfeImportService::class)->importForOrganization($org->id, $note);
    $events = [sample_nfe_event_xml(sync_chave('1')), sample_nfe_event_xml(sync_chave('1'), 110110, '135', 1, null, 'Correcao')];
    sync_fake_periodo(fn (array $b) => $b['Skip'] === 0 ? Http::response(sync_zip($events)) : null);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->requests)->toBe(1)->and($run->events)->toBe(2);
});

test('periodo: deadline mid-window keeps window and skip, next run resumes there', function () {
    $account = sync_account(sync_org(), ['mode' => 'periodo']);
    $calls = 0;
    sync_fake_periodo(function (array $b) use (&$calls) {
        $calls++;
        if ($calls === 1) {
            $this->travel(200)->seconds();

            return Http::response(sync_zip([sync_note('1'), sync_note('2')]));
        }

        return null;
    });

    $run = ($this->service)()->sync($account, now()->addSeconds(100));
    $cursor = SiegSyncCursor::query()->sole();

    expect($run->status)->toBe('partial')->and($cursor->skip)->toBe(2)
        ->and($cursor->window_start)->not->toBeNull()->and($cursor->window_end)->not->toBeNull();

    $next = ($this->service)()->sync($account->fresh(), ($this->deadline)());
    expect($next->status)->toBe('ok');
    expect(sync_requests('/baixar-xmls')[1]->data()['Skip'])->toBe(2);
});

test('periodo/branch: one cursor per branch x role with Cnpj filters', function () {
    $org = sync_org(SYNC_ORG, [SYNC_ORG_B2]);
    $account = sync_account($org, ['mode' => 'periodo']);
    config(['sieg.sync.periodo_scope' => 'branch']);
    Http::fake(['*/create-jwt' => Http::response('jwt', 200), '*/baixar-xmls' => Http::response('', 404)]);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    $pairs = array_map(function (Request $r) {
        $d = $r->data();

        return isset($d['CnpjDest']) ? 'dest:'.$d['CnpjDest'] : 'emit:'.$d['CnpjEmit'];
    }, sync_requests('/baixar-xmls'));
    sort($pairs);
    expect($run->status)->toBe('ok')->and($run->requests)->toBe(4)
        ->and($pairs)->toBe(['dest:'.SYNC_ORG, 'dest:'.SYNC_ORG_B2, 'emit:'.SYNC_ORG, 'emit:'.SYNC_ORG_B2])
        ->and(SiegSyncCursor::query()->count())->toBe(4);
    foreach (sync_requests('/baixar-xmls') as $r) {
        expect(isset($r->data()['CnpjDest'], $r->data()['CnpjEmit']))->toBeFalse();
    }
});

// ------------------------------------------------------------ lock / pausa

test('skips when the account lock is held', function () {
    $account = sync_account(sync_org());
    Http::fake();
    $lock = Cache::lock("sieg:sync:account:{$account->id}", 60);
    expect($lock->get())->toBeTrue();

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('skipped')->and($run->message)->toContain('execução em andamento');
    Http::assertNothingSent();
    $lock->release();
});

test('releases the lock after a run, even when it fails', function () {
    $account = sync_account(sync_org());
    Http::fake(['*/create-jwt' => Http::response('', 500), '*' => Http::response('', 500)]);

    $run = ($this->service)()->sync($account, ($this->deadline)());

    expect($run->status)->toBe('failed');
    $lock = Cache::lock("sieg:sync:account:{$account->id}", 5);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

test('paused accounts are skipped unless forced; disabled never run', function () {
    $org = sync_org();
    $paused = sync_account($org, ['paused_until' => now()->addHour()]);
    $disabled = sync_account($org, ['enabled' => false, 'paused_until' => now()->addHour()]);
    sync_fake_nsu(0, 0, fn () => []);

    expect(($this->service)()->sync($paused, ($this->deadline)())->status)->toBe('skipped');
    expect(($this->service)()->sync($paused, ($this->deadline)(), true)->status)->toBe('ok');
    expect(($this->service)()->sync($disabled, ($this->deadline)(), true)->status)->toBe('skipped');
    expect(($this->service)()->sync($disabled, ($this->deadline)())->message)->toContain('desabilitada');
    expect($disabled->fresh()->last_run_at)->toBeNull();
});
