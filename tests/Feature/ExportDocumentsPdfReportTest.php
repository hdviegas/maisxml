<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

const PDFR_CNPJ_A = '11222333000181';
const PDFR_CNPJ_B = '44555666000199';
const PDFR_SUPPLIER = '99888777000166';

/** @return array{user: User, branch: Branch} */
function pdfr_tenant(string $cnpj, string $name, string $email): array
{
    $org = Organization::query()->create(['social' => $name, 'fantasia' => $name, 'cnpj' => $cnpj]);
    $user = User::factory()->create(['cnpj' => $cnpj, 'email' => $email]);
    $branch = Branch::query()->create(['empresa' => $org->id, 'social' => $name.' Filial', 'fantasia' => null, 'cnpj' => $cnpj, 'entrada' => true]);

    return ['user' => $user, 'branch' => $branch];
}

function pdfr_doc(string $fill, string $emit, string $dest, ?string $cfop = '5102'): FiscalDocument
{
    return FiscalDocument::query()->create([
        'url' => 'pdfr_x',
        'chave' => strlen($fill) > 1
            ? substr(chave_for('1', $emit), 0, 20).str_pad($fill, 24, '0', STR_PAD_LEFT)
            : chave_for($fill, $emit),
        'data_nf' => now()->subDay()->toDateString(),
        'cnpj_emit' => $emit,
        'cnpj_dest' => $dest,
        'social_emit' => 'pdfr_Emitente '.$fill,
        'social_dest' => 'pdfr_Destino '.$fill,
        'total_nf' => 100.5,
        'cfop_nf' => $cfop,
        'modelo_nf' => '55',
        'numero_nf' => (int) substr($fill, -4),
        'serie_nf' => 1,
    ]);
}

function pdfr_count_queries(callable $fn): int
{
    $n = 0;
    DB::listen(function () use (&$n): void {
        $n++;
    });
    $fn();

    return $n;
}

test('pdf report downloads a valid pdf with the expected file name', function () {
    $a = pdfr_tenant(PDFR_CNPJ_A, 'pdfr_A', 'pdfr_a@example.com');
    pdfr_doc('1', PDFR_CNPJ_A, PDFR_SUPPLIER);

    $res = $this->actingAs($a['user'])->get(route('documents.export.pdf'));

    $res->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('relatorio-documentos-'.now()->format('Y-m-d').'.pdf')
        ->and(substr($res->getContent(), 0, 4))->toBe('%PDF');
});

test('pdf report with no matching rows still renders', function () {
    $a = pdfr_tenant(PDFR_CNPJ_A, 'pdfr_A', 'pdfr_a@example.com');

    $res = $this->actingAs($a['user'])->get(route('documents.export.pdf'));

    $res->assertOk();
    expect(substr($res->getContent(), 0, 4))->toBe('%PDF');
});

test('pdf report query count does not grow with the number of rows', function () {
    $a = pdfr_tenant(PDFR_CNPJ_A, 'pdfr_A', 'pdfr_a@example.com');
    pdfr_doc('1', PDFR_CNPJ_A, PDFR_SUPPLIER);
    pdfr_doc('2', PDFR_SUPPLIER, PDFR_CNPJ_A);

    $few = pdfr_count_queries(fn () => $this->actingAs($a['user'])->get(route('documents.export.pdf'))->assertOk());

    foreach (range(3, 40) as $i) {
        pdfr_doc('9'.$i, $i % 2 ? PDFR_CNPJ_A : PDFR_SUPPLIER, $i % 2 ? PDFR_SUPPLIER : PDFR_CNPJ_A);
    }

    $many = pdfr_count_queries(fn () => $this->actingAs($a['user'])->get(route('documents.export.pdf'))->assertOk());

    expect($many)->toBe($few);
});

test('pdf report resolves the branch for inbound documents from the recipient', function () {
    $a = pdfr_tenant(PDFR_CNPJ_A, 'pdfr_A', 'pdfr_a@example.com');
    pdfr_doc('1', PDFR_SUPPLIER, PDFR_CNPJ_A, '1102');

    $captured = null;
    View::composer('pdf.documents-list', function ($view) use (&$captured): void {
        $captured = $view->getData()['rows'];
    });

    $this->actingAs($a['user'])->get(route('documents.export.pdf'))->assertOk();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['branch'])->toBe('pdfr_A Filial');
});

test('pdf report never includes documents of another tenant', function () {
    $a = pdfr_tenant(PDFR_CNPJ_A, 'pdfr_A', 'pdfr_a@example.com');
    pdfr_tenant(PDFR_CNPJ_B, 'pdfr_B', 'pdfr_b@example.com');
    pdfr_doc('1', PDFR_CNPJ_A, PDFR_SUPPLIER);
    pdfr_doc('2', PDFR_CNPJ_B, PDFR_SUPPLIER);
    pdfr_doc('3', PDFR_SUPPLIER, PDFR_CNPJ_B);

    $captured = null;
    View::composer('pdf.documents-list', function ($view) use (&$captured): void {
        $captured = $view->getData()['rows'];
    });

    $this->actingAs($a['user'])->get(route('documents.export.pdf'))->assertOk();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['chave'])->toBe(chave_for('1', PDFR_CNPJ_A))
        ->and($captured[0]['branch'])->toBe('pdfr_A Filial');
});

test('pdf report shows sped column only when a sped filter is active', function () {
    $a = pdfr_tenant(PDFR_CNPJ_A, 'pdfr_A', 'pdfr_a@example.com');
    pdfr_doc('1', PDFR_CNPJ_A, PDFR_SUPPLIER);

    $flags = [];
    View::composer('pdf.documents-list', function ($view) use (&$flags): void {
        $flags[] = $view->getData()['with_sped'];
    });

    $this->actingAs($a['user'])->get(route('documents.export.pdf'))->assertOk();
    $this->actingAs($a['user'])->get(route('documents.export.pdf', ['sped_reconcile' => 'none']))->assertOk();

    expect($flags)->toBe([false, true]);
});
