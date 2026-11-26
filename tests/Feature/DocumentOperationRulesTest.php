<?php

use App\Enums\DocumentOperationType as Op;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Documents\DocumentListFilters;
use App\Services\Documents\DocumentListQuery;
use App\Services\Documents\DocumentOperationRules;
use Illuminate\Support\Facades\DB;

const OPR_A1 = '71000000000101';   // branch (root 71000000)
const OPR_A2 = '71000000000202';   // branch, same root
const OPR_SUP = '73000000000101';  // supplier / customer
const OPR_OTHER = '74000000000101';
const OPR_B2 = '75000000000101';   // branch of another root (same user org)

function opr_world(): array
{
    $org = Organization::query()->create(['social' => 'opr', 'fantasia' => 'opr', 'cnpj' => OPR_A1]);
    $user = User::factory()->create(['cnpj' => OPR_A1, 'email' => 'opr@example.com']);
    foreach ([OPR_A1, OPR_A2, OPR_B2] as $c) {
        Branch::query()->create(['empresa' => $org->id, 'social' => 'f'.$c, 'fantasia' => null, 'cnpj' => $c, 'entrada' => true]);
    }

    $mk = fn (string $fill, string $emit, string $dest, string $cfop) => FiscalDocument::query()->create(legacy_xml_row([
        'chave' => chave_for($fill, $emit),
        'data_nf' => '2024-06-01',
        'cnpj_emit' => $emit,
        'cnpj_dest' => $dest,
        'cfop_nf' => $cfop,
    ]));

    $docs = [
        'sale' => $mk('1', OPR_A1, OPR_SUP, '5102'),
        'purchase' => $mk('2', OPR_SUP, OPR_A1, '5102'),
        'transfer' => $mk('3', OPR_A1, OPR_A2, '5152'),
        'entry_note' => $mk('4', OPR_A1, OPR_SUP, '1202'),
        'cfop5117' => $mk('5', OPR_A1, OPR_SUP, '5117'),
        'unrelated' => $mk('6', OPR_SUP, OPR_OTHER, '5102'),
        'cross_root' => $mk('7', OPR_B2, OPR_A1, '5152'),   // branch (root Y) -> branch (root X)
        'nfce_empty' => $mk('8', OPR_A1, '', '5102'),
        'nfce_null' => $mk('9', OPR_A1, '', '5102'),
    ];

    // legacy NFC-e rows may hold NULL in cnpj_dest
    DB::table('xml')->where('id', $docs['nfce_null']->id)->update(['cnpj_dest' => null]);

    return [$user, $docs];
}

function opr_keys(User $user, array $docs, Op $op): array
{
    $q = FiscalDocument::query();
    DocumentOperationRules::applyFilter($q, $op, DocumentListQuery::cnpjDigitsForUser($user));
    $ids = $q->pluck('id')->all();

    return collect($docs)->filter(fn ($d) => in_array($d->id, $ids))->keys()->sort()->values()->all();
}

test('operation filters follow the role-based legacy rule (overlaps included)', function (Op $op, array $expected) {
    [$user, $docs] = opr_world();

    expect(opr_keys($user, $docs, $op))->toBe($expected);
})->with([
    'compras' => [Op::Compras, ['purchase']],
    'saida' => [Op::Saida, ['entry_note', 'nfce_empty', 'nfce_null', 'sale']],
    'transferencia' => [Op::Transferencia, ['cross_root', 'transfer']],
    'entradas gerais' => [Op::EntradasGerais, ['cross_root', 'entry_note', 'purchase', 'transfer']],
    'devolucoes' => [Op::Devolucoes, ['entry_note']],
]);

test('operation label is a single role-based value', function () {
    [$user, $docs] = opr_world();
    $digits = DocumentListQuery::cnpjDigitsForUser($user);
    $label = fn (string $k) => DocumentOperationRules::labelFor($docs[$k], $digits);

    expect($label('sale'))->toBe(Op::Saida)
        ->and($label('purchase'))->toBe(Op::Compras)
        ->and($label('transfer'))->toBe(Op::Transferencia)
        ->and($label('entry_note'))->toBe(Op::Devolucoes)
        ->and($label('cfop5117'))->toBe(Op::Saida)
        ->and($label('cross_root'))->toBe(Op::Transferencia)
        ->and($label('nfce_empty'))->toBe(Op::Saida)
        ->and($label('nfce_null'))->toBe(Op::Saida)
        ->and($label('unrelated'))->toBeNull();
});

test('authenticated model accessor uses role-based label, not cfop', function () {
    [$user, $docs] = opr_world();

    expect($docs['purchase']->operation_type)->toBe(Op::Saida); // no user: cfop fallback
    $this->actingAs($user);
    expect($docs['purchase']->fresh()->operation_type)->toBe(Op::Compras);
});

test('documents page and api filter by operation_type', function () {
    [$user, $docs] = opr_world();
    $token = $user->createToken('opr')->plainTextToken;

    $get = fn (int $op) => collect(
        $this->getJson('/api/v1/documents?issued_from=2024-01-01&issued_to=2024-12-31&operation_type='.$op, [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()->json('data')
    );

    $compras = $get(1);
    expect($compras->pluck('chave')->all())->toBe([$docs['purchase']->chave])
        ->and($compras->first()['operation_type'])->toBe(1);
    expect($get(3)->pluck('chave')->sort()->values()->all())
        ->toBe(collect([$docs['transfer']->chave, $docs['cross_root']->chave])->sort()->values()->all());
    expect($get(99))->toHaveCount(8); // out-of-range value is ignored by DocumentListFilters

    $this->actingAs($user)
        ->get('/documentos?issued_from=2024-01-01&issued_to=2024-12-31&operation_type=1')
        ->assertOk()
        ->assertSee($docs['purchase']->chave, false)
        ->assertDontSee($docs['sale']->chave, false);
});

test('empty branch set matches nothing', function () {
    [, $docs] = opr_world();
    $q = FiscalDocument::query();
    DocumentOperationRules::applyFilter($q, Op::Saida, []);
    expect($q->count())->toBe(0);
});
