<?php

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Nfe\NfeImportService;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

const BDF_CNPJ_A = '11222333000181';
const BDF_CNPJ_B = '44555666000199';
const BDF_SUPPLIER = '99888777000166';

function bdf_tenant(string $cnpj, string $name): array
{
    $org = Organization::query()->create(['social' => $name, 'fantasia' => $name, 'cnpj' => $cnpj]);
    $user = User::factory()->create(['cnpj' => $cnpj, 'email' => 'bdf_'.strtolower($name).'@example.com']);
    $branch = Branch::query()->create([
        'empresa' => $org->id, 'social' => $name.' Matriz', 'fantasia' => null, 'cnpj' => $cnpj, 'entrada' => true,
    ]);

    return ['user' => $user, 'branch' => $branch];
}

function bdf_doc(array $tenant, string $cnpj, string $fill): FiscalDocument
{
    $chave = chave_for($fill, BDF_SUPPLIER);
    $xml = sample_nfe_xml($chave, BDF_SUPPLIER, $cnpj);

    return app(NfeImportService::class)->importForBranch($tenant['user'], $tenant['branch'], $xml)->document;
}

function bdf_page_count(string $pdf): int
{
    return (new Fpdi)->setSourceFile(StreamReader::createByString($pdf));
}

beforeEach(function () {
    Storage::fake('local');
});

test('bulk danfe merges selected documents into one pdf', function () {
    $a = bdf_tenant(BDF_CNPJ_A, 'Alpha');
    $d1 = bdf_doc($a, BDF_CNPJ_A, '1');
    $d2 = bdf_doc($a, BDF_CNPJ_A, '2');

    $response = $this->actingAs($a['user'])->post(route('documents.bulk_danfe'), ['ids' => [$d1->id, $d2->id]]);

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toMatch('/^inline; filename="DANFEs-\d{4}-\d{2}-\d{2}-\d{4}\.pdf"$/');
    $pdf = $response->getContent();
    expect(mb_substr($pdf, 0, 4))->toBe('%PDF');
    expect(bdf_page_count($pdf))->toBeGreaterThanOrEqual(2);
});

test('bulk danfe rejects documents of another tenant', function () {
    $a = bdf_tenant(BDF_CNPJ_A, 'Alpha');
    $b = bdf_tenant(BDF_CNPJ_B, 'Beta');
    $mine = bdf_doc($a, BDF_CNPJ_A, '3');
    $theirs = bdf_doc($b, BDF_CNPJ_B, '4');

    $this->actingAs($a['user'])
        ->post(route('documents.bulk_danfe'), ['ids' => [$mine->id, $theirs->id]])
        ->assertForbidden();
});

test('bulk danfe validates ids', function () {
    $a = bdf_tenant(BDF_CNPJ_A, 'Alpha');

    $this->actingAs($a['user'])->post(route('documents.bulk_danfe'), ['ids' => []])
        ->assertSessionHasErrors('ids');
    $this->actingAs($a['user'])->post(route('documents.bulk_danfe'), [])
        ->assertSessionHasErrors('ids');

    config(['documents.bulk_danfe_max' => 1]);
    $d1 = bdf_doc($a, BDF_CNPJ_A, '5');
    $d2 = bdf_doc($a, BDF_CNPJ_A, '6');
    $this->actingAs($a['user'])->post(route('documents.bulk_danfe'), ['ids' => [$d1->id, $d2->id]])
        ->assertSessionHasErrors('ids');
});

test('bulk danfe skips documents without xml and fails friendly when none render', function () {
    $a = bdf_tenant(BDF_CNPJ_A, 'Alpha');
    $ok = bdf_doc($a, BDF_CNPJ_A, '7');
    $gone = bdf_doc($a, BDF_CNPJ_A, '8');
    Storage::disk('local')->delete($gone->storage_path);

    $response = $this->actingAs($a['user'])->post(route('documents.bulk_danfe'), ['ids' => [$gone->id, $ok->id]]);
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(bdf_page_count($response->getContent()))->toBeGreaterThanOrEqual(1);

    $log = ActivityLog::query()->where('action', 'documents.bulk_danfe')->latest('id')->first();
    expect($log->properties['skipped_chaves'])->toBe([$gone->chave]);

    $this->actingAs($a['user'])->from(route('documents.index'))
        ->post(route('documents.bulk_danfe'), ['ids' => [$gone->id]])
        ->assertRedirect(route('documents.index'))
        ->assertSessionHasErrors('ids');
});

test('bulk danfe keeps per-document page sizes when mixing nfe and nfce', function () {
    $a = bdf_tenant(BDF_CNPJ_A, 'Alpha');
    $nfe = bdf_doc($a, BDF_CNPJ_A, '9');

    $chave = chave_for('a', BDF_CNPJ_A);
    $path = 'xml/'.BDF_CNPJ_A.'/bdf/'.$chave.'.xml';
    Storage::disk('local')->put($path, sample_nfce_xml($chave, BDF_CNPJ_A));
    $nfce = FiscalDocument::query()->create([
        'url' => $path, 'chave' => $chave, 'empresa' => BDF_CNPJ_A, 'data_nf' => now(), 'ip' => '127.0.0.1',
        'cnpj_emit' => BDF_CNPJ_A, 'numero_nf' => 1, 'modelo_nf' => '65', 'serie_nf' => '1', 'total_nf' => '10.00',
        'cfop_nf' => '5102', 'cancelada' => false,
    ]);

    $response = $this->actingAs($a['user'])->post(route('documents.bulk_danfe'), ['ids' => [$nfe->id, $nfce->id]]);
    $response->assertOk();

    $fpdi = new Fpdi;
    $n = $fpdi->setSourceFile(StreamReader::createByString($response->getContent()));
    $widths = [];
    for ($i = 1; $i <= $n; $i++) {
        $widths[] = round($fpdi->getTemplateSize($fpdi->importPage($i))['width']);
    }
    expect($widths[0])->toBe(210.0)->and(min($widths))->toBeLessThan(150.0);
});
