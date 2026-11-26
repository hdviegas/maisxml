<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Documents\BranchNameResolver;

const BFN_CNPJ = '74000000000101';

function bfn_world(): array
{
    $org = Organization::query()->create(['social' => 'bfn_Org', 'fantasia' => 'bfn_Org', 'cnpj' => BFN_CNPJ]);
    $user = User::factory()->create(['cnpj' => BFN_CNPJ, 'email' => 'bfn@example.com']);
    $b1 = Branch::query()->create(['empresa' => $org->id, 'social' => 'AAA Razao Social Ltda', 'fantasia' => 'Zeta Fantasia', 'cnpj' => BFN_CNPJ, 'entrada' => true]);
    $b2 = Branch::query()->create(['empresa' => $org->id, 'social' => 'ZZZ Razao Social Ltda', 'fantasia' => 'Alfa Fantasia', 'cnpj' => '74000000000202', 'entrada' => true]);

    return compact('user', 'b1', 'b2');
}

test('branch name accessor prefers fantasia and falls back to social when blank', function () {
    $w = bfn_world();
    expect($w['b1']->name)->toBe('Zeta Fantasia');

    $w['b1']->fantasia = '   ';
    expect($w['b1']->name)->toBe('AAA Razao Social Ltda');

    $w['b1']->fantasia = null;
    expect($w['b1']->name)->toBe('AAA Razao Social Ltda');
});

test('documents page branch filter lists fantasia sorted by it', function () {
    $w = bfn_world();

    $this->actingAs($w['user'])->get('/documentos')
        ->assertOk()
        ->assertSeeInOrder(['Alfa Fantasia', 'Zeta Fantasia'])
        ->assertDontSee('AAA Razao Social Ltda');
});

test('import and sped branch selects show fantasia', function () {
    $w = bfn_world();

    foreach (['/importar', '/sped'] as $url) {
        $this->actingAs($w['user'])->get($url)->assertOk()
            ->assertSeeInOrder(['Alfa Fantasia', 'Zeta Fantasia'])
            ->assertDontSee('ZZZ Razao Social Ltda');
    }
});

test('branch name resolver and api show use fantasia', function () {
    $w = bfn_world();

    $resolver = BranchNameResolver::forUser($w['user']);
    $names = (new ReflectionClass($resolver))->getProperty('namesByCode')->getValue($resolver);
    expect($names[(int) $w['b1']->codigo])->toBe('Zeta Fantasia');

    $doc = FiscalDocument::query()->create([
        'url' => 'x', 'chave' => str_repeat('1', 44), 'empresa' => BFN_CNPJ, 'social' => '', 'dest' => '', 'tipo' => '',
        'data_nf' => now(), 'ip' => '127.0.0.1', 'cnpj_emit' => BFN_CNPJ, 'cnpj_dest' => '12345678901',
        'numero_nf' => 1, 'modelo_nf' => '55', 'serie_nf' => '1', 'total_nf' => '1', 'total_icms' => 0,
        'total_frete' => 0, 'cfop_nf' => '5102', 'cancelada' => false, 'branch_id' => $w['b1']->codigo,
    ]);
    $token = $w['user']->createToken('bfn')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/documents/{$doc->id}")
        ->assertOk()->assertJsonPath('branch.name', 'Zeta Fantasia');
});
