<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Documents\DocumentListFilters;
use App\Services\Documents\DocumentListQuery;
use Illuminate\Support\Facades\DB;

const DLP_CNPJ = '11222333000181';
const DLP_SUPPLIER = '99888777000166';

function dlp_setup(): User
{
    $org = Organization::query()->create(['social' => 'Perf', 'fantasia' => 'Perf', 'cnpj' => DLP_CNPJ]);
    $user = User::factory()->create(['cnpj' => DLP_CNPJ, 'email' => 'perf@example.com']);
    Branch::query()->create(['empresa' => $org->id, 'social' => 'Perf', 'fantasia' => null, 'cnpj' => DLP_CNPJ, 'entrada' => true]);

    return $user;
}

function dlp_doc(string $fill, string $dataNf, string $dest = DLP_CNPJ): FiscalDocument
{
    return FiscalDocument::query()->create(legacy_xml_row([
        'url' => 'x',
        'chave' => chave_for($fill, DLP_SUPPLIER),
        'data_nf' => $dataNf,
        'cnpj_emit' => DLP_SUPPLIER,
        'cnpj_dest' => $dest,
    ]));
}

test('list SQL has no REPLACE or DATE wrappers', function () {
    $user = dlp_setup();
    $q = DocumentListQuery::forUser($user);
    DocumentListQuery::applyFilters($q, new DocumentListFilters(
        branchIds: [(int) Branch::query()->value('codigo')],
        issuedFrom: '2026-01-01',
        issuedTo: '2026-01-31',
        spedReconcile: 'ok',
    ), $user);
    DocumentListQuery::withSpedColumns($q, $user);

    $sql = strtoupper($q->toSql());
    expect($sql)->not->toContain('REPLACE(')->and($sql)->not->toContain('DATE(');
});

test('issued_to includes the whole end day', function () {
    $user = dlp_setup();
    dlp_doc('1', '2026-03-10 23:59:59');
    dlp_doc('2', '2026-03-11 00:00:00');

    $this->actingAs($user)
        ->get(route('documents.index', ['issued_from' => '2026-03-10', 'issued_to' => '2026-03-10']))
        ->assertOk()
        ->assertSee(chave_for('1', DLP_SUPPLIER))
        ->assertDontSee(chave_for('2', DLP_SUPPLIER));
});

test('default period is applied, shown in the form and carried to exports', function () {
    $user = dlp_setup();
    $today = now()->toDateString();
    $from = now()->subDays(max(1, (int) config('dashboard.default_period_days', 30)) - 1)->toDateString();
    dlp_doc('3', now()->subDays(2)->toDateString());
    dlp_doc('4', now()->subDays(200)->toDateString());

    $this->actingAs($user)->get(route('documents.index'))
        ->assertOk()
        ->assertSee(chave_for('3', DLP_SUPPLIER))
        ->assertDontSee(chave_for('4', DLP_SUPPLIER))
        ->assertSee('value="'.$from.'"', false)
        ->assertSee('value="'.$today.'"', false)
        ->assertSee('issued_from='.$from, false);
});

test('full 44-digit key search skips the default period and finds old notes', function () {
    $user = dlp_setup();
    $old = dlp_doc('5', now()->subDays(400)->toDateString());

    $this->actingAs($user)->get(route('documents.index', ['q' => $old->chave]))
        ->assertOk()
        ->assertSee($old->chave);

    $q = DocumentListQuery::forUser($user);
    DocumentListQuery::applyFilters($q, new DocumentListFilters(q: $old->chave), $user);
    expect($q->toSql())->toContain('"xml"."chave" = ?');
});

test('branch with leading-space cnpj is still recognised', function () {
    $user = dlp_setup();
    Branch::query()->delete();
    $org = Organization::query()->firstOrFail();
    Branch::query()->create(['empresa' => $org->id, 'social' => 'F', 'fantasia' => null, 'cnpj' => ' '.DLP_CNPJ, 'entrada' => false]);
    dlp_doc('6', now()->subDay()->toDateString());

    expect(Branch::codesMatchingCnpj(DLP_CNPJ))->toHaveCount(1);
    expect(DocumentListQuery::forUser($user)->count())->toBe(1);
});

test('organization scope matches formatted cnpj via PHP normalization', function () {
    $user = dlp_setup();
    Organization::query()->update(['cnpj' => '11.222.333/0001-81']);

    expect(Organization::query()->forUser($user)->count())->toBe(1);
    expect(DB::table('empresa')->count())->toBe(1);
});
