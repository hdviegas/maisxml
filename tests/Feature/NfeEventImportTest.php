<?php

use App\Enums\OnDuplicateImport;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\FiscalEvent;
use App\Models\Organization;
use App\Services\Nfe\NfeEventImporter;
use App\Services\Nfe\NfeEventParser;
use App\Services\Nfe\NfeImportService;
use Illuminate\Support\Facades\Storage;

const EV_ORG_A = '11222333000181';
const EV_ORG_A2 = '11222333000262';
const EV_ORG_B = '44555666000199';
const EV_SUPPLIER = '99888777000166';

function ev_org(string $cnpj, string $name, array $extraBranches = []): Organization
{
    $org = Organization::query()->create(['social' => $name, 'fantasia' => $name, 'cnpj' => $cnpj]);
    foreach ([$cnpj, ...$extraBranches] as $c) {
        Branch::query()->create(['empresa' => $org->id, 'social' => $name, 'fantasia' => null, 'cnpj' => $c, 'entrada' => true]);
    }

    return $org;
}

beforeEach(function () {
    Storage::fake('local');
});

test('parser reads event and retEvento fields', function () {
    $chave = chave_for('1', EV_ORG_A);
    $e = app(NfeEventParser::class)->parse(sample_nfe_event_xml($chave, 110110, '135', 2, null, 'Correcao do campo X'));

    expect($e->chave)->toBe($chave)
        ->and($e->tpEvento)->toBe(110110)
        ->and($e->nSeqEvento)->toBe(2)
        ->and($e->authorDoc)->toBe(EV_ORG_A)
        ->and($e->descricao)->toBe('Carta de Correcao')
        ->and($e->xCorrecao)->toBe('Correcao do campo X')
        ->and($e->cStat)->toBe('135')
        ->and($e->protocolo)->toBe('135240000099999')
        ->and($e->dhEvento)->not->toBeNull()
        ->and($e->isCancellation())->toBeFalse();
});

test('parser rejects an NF-e as not_an_event', function () {
    app(NfeEventParser::class)->parse(sample_nfe_xml(chave_for('1', EV_ORG_A), EV_ORG_A));
})->throws(InvalidArgumentException::class, 'not_an_event');

test('parser rejects malformed chave as invalid_event', function () {
    $xml = str_replace(chave_for('1', EV_ORG_A), '123', sample_nfe_event_xml(chave_for('1', EV_ORG_A)));
    app(NfeEventParser::class)->parse($xml);
})->throws(InvalidArgumentException::class, 'invalid_event');

test('cancellation event marks owned document cancelada', function () {
    $org = ev_org(EV_ORG_A, 'A');
    $chave = chave_for('1', EV_ORG_A);
    $doc = app(NfeImportService::class)->importForOrganization($org->id, sample_nfe_xml($chave, EV_ORG_A, EV_SUPPLIER))->document;
    expect($doc->cancelada)->toBeFalse();

    $r = app(NfeEventImporter::class)->import($org->id, sample_nfe_event_xml($chave));

    expect($r)->toBe('created')
        ->and($doc->fresh()->cancelada)->toBeTrue()
        ->and(FiscalEvent::query()->cancellations()->count())->toBe(1);
    Storage::disk('local')->assertExists("eventos/{$chave}/110111-1.xml");
    expect(ActivityLog::query()->where('action', 'fiscal_event.imported')->count())->toBe(1);
});

test('event arriving before the note (authored by org) cancels the note on import', function () {
    $org = ev_org(EV_ORG_A, 'A');
    $chave = chave_for('2', EV_ORG_A);

    expect(app(NfeEventImporter::class)->import($org->id, sample_nfe_event_xml($chave)))->toBe('created');

    $doc = app(NfeImportService::class)->importForOrganization($org->id, sample_nfe_xml($chave, EV_ORG_A, EV_SUPPLIER))->document;
    expect($doc->cancelada)->toBeTrue();
});

test('event for another org document is not_owned and stores nothing', function () {
    $a = ev_org(EV_ORG_A, 'A');
    $b = ev_org(EV_ORG_B, 'B');
    $chave = chave_for('3', EV_SUPPLIER);
    $doc = app(NfeImportService::class)->importForOrganization($b->id, sample_nfe_xml($chave, EV_SUPPLIER, EV_ORG_B))->document;

    $r = app(NfeEventImporter::class)->import($a->id, sample_nfe_event_xml($chave));

    expect($r)->toBe('not_owned')
        ->and(FiscalEvent::query()->count())->toBe(0)
        ->and($doc->fresh()->cancelada)->toBeFalse();
    Storage::disk('local')->assertMissing("eventos/{$chave}/110111-1.xml");
});

test('inbound event authored by the supplier before the note is not_owned', function () {
    $a = ev_org(EV_ORG_A, 'A');
    $chave = chave_for('4', EV_SUPPLIER);

    expect(app(NfeEventImporter::class)->import($a->id, sample_nfe_event_xml($chave)))->toBe('not_owned');
});

test('duplicate event returns duplicate', function () {
    $org = ev_org(EV_ORG_A, 'A');
    $chave = chave_for('5', EV_ORG_A);
    $xml = sample_nfe_event_xml($chave);

    expect(app(NfeEventImporter::class)->import($org->id, $xml))->toBe('created')
        ->and(app(NfeEventImporter::class)->import($org->id, $xml))->toBe('duplicate')
        ->and(FiscalEvent::query()->count())->toBe(1);
});

test('CC-e is stored with x_correcao and does not cancel', function () {
    $org = ev_org(EV_ORG_A, 'A');
    $chave = chave_for('6', EV_ORG_A);
    $doc = app(NfeImportService::class)->importForOrganization($org->id, sample_nfe_xml($chave, EV_ORG_A, EV_SUPPLIER))->document;

    $r = app(NfeEventImporter::class)->import($org->id, sample_nfe_event_xml($chave, 110110, '135', 1, null, 'Corrigir endereco'));

    expect($r)->toBe('created')
        ->and(FiscalEvent::query()->first()->x_correcao)->toBe('Corrigir endereco')
        ->and($doc->fresh()->cancelada)->toBeFalse();
});

test('NF-e with protNFe cStat 101 imports as cancelada', function () {
    $org = ev_org(EV_ORG_A, 'A');
    $chave = chave_for('7', EV_ORG_A);
    $xml = str_replace('<cStat>100</cStat>', '<cStat>101</cStat>', sample_nfe_xml($chave, EV_ORG_A, EV_SUPPLIER));

    $doc = app(NfeImportService::class)->importForOrganization($org->id, $xml)->document;

    expect($doc->cancelada)->toBeTrue();
});

test('importForOrganization picks the recipient branch', function () {
    $org = ev_org(EV_ORG_A, 'A', [EV_ORG_A2]);
    $chave = chave_for('8', EV_ORG_A);
    $outcome = app(NfeImportService::class)->importForOrganization($org->id, sample_nfe_xml($chave, EV_ORG_A, EV_ORG_A2));

    expect($outcome->wasCreated())->toBeTrue()
        ->and($outcome->document->getRawOriginal('cnpj_dest'))->toBe(EV_ORG_A2);
});

test('importForOrganization rejects foreign xml', function () {
    $org = ev_org(EV_ORG_A, 'A');
    $xml = sample_nfe_xml(chave_for('9', EV_SUPPLIER), EV_SUPPLIER, EV_ORG_B);

    app(NfeImportService::class)->importForOrganization($org->id, $xml);
})->throws(InvalidArgumentException::class, 'xml_not_for_organization');

test('importForOrganization skips duplicates and logs via sieg', function () {
    $org = ev_org(EV_ORG_A, 'A');
    $xml = sample_nfe_xml(chave_for('1', EV_SUPPLIER), EV_SUPPLIER, EV_ORG_A);
    $svc = app(NfeImportService::class);

    expect($svc->importForOrganization($org->id, $xml)->wasCreated())->toBeTrue()
        ->and($svc->importForOrganization($org->id, $xml, OnDuplicateImport::Skip)->wasSkipped())->toBeTrue()
        ->and(FiscalDocument::query()->count())->toBe(1);

    $log = ActivityLog::query()->where('action', 'fiscal_document.imported')->first();
    expect($log->properties['via'])->toBe('sieg');
});

test('importForOrganization refuses a chave owned by another org', function () {
    $a = ev_org(EV_ORG_A, 'A');
    $b = ev_org(EV_ORG_B, 'B');
    $chave = chave_for('1', EV_SUPPLIER);
    $svc = app(NfeImportService::class);
    $svc->importForOrganization($b->id, sample_nfe_xml($chave, EV_SUPPLIER, EV_ORG_B));

    $svc->importForOrganization($a->id, sample_nfe_xml($chave, EV_SUPPLIER, EV_ORG_A));
})->throws(RuntimeException::class, 'duplicate_chave_other_org');
