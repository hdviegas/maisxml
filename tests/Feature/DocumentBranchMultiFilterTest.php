<?php

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\Nfe\NfeImportService;
use Illuminate\Support\Facades\Storage;

const BMS_A1 = '71000000000101';
const BMS_A2 = '71000000000202';
const BMS_A3 = '71000000000303';
const BMS_B1 = '72000000000101';
const BMS_SUP = '73000000000101';

/**
 * Org A (3 branches) + org B (1 branch), one document per branch.
 *
 * @return array<string, mixed>
 */
function bms_world(): array
{
    Storage::fake('local');
    $svc = app(NfeImportService::class);

    $orgA = Organization::query()->create(['social' => 'bms_A', 'fantasia' => 'bms_A', 'cnpj' => BMS_A1]);
    $orgB = Organization::query()->create(['social' => 'bms_B', 'fantasia' => 'bms_B', 'cnpj' => BMS_B1]);
    $userA = User::factory()->create(['cnpj' => BMS_A1, 'email' => 'bms_a@example.com']);
    $userB = User::factory()->create(['cnpj' => BMS_B1, 'email' => 'bms_b@example.com']);

    $mk = fn (Organization $o, string $cnpj, string $n) => Branch::query()->create([
        'empresa' => $o->id, 'social' => $n, 'fantasia' => null, 'cnpj' => $cnpj, 'entrada' => true,
    ]);
    $b1 = $mk($orgA, BMS_A1, 'bms_f1');
    $b2 = $mk($orgA, BMS_A2, 'bms_f2');
    $b3 = $mk($orgA, BMS_A3, 'bms_f3');
    $bx = $mk($orgB, BMS_B1, 'bms_fx');

    // f1 receives (dest), f2 issues (emit), f3 and fx receive.
    $c1 = chave_for('1', BMS_SUP);
    $c2 = chave_for('2', BMS_A2);
    $c3 = chave_for('3', BMS_SUP);
    $cx = chave_for('4', BMS_SUP);
    $svc->importForBranch($userA, $b1, sample_nfe_xml($c1, BMS_SUP, BMS_A1));
    $svc->importForBranch($userA, $b2, sample_nfe_xml($c2, BMS_A2, BMS_SUP));
    $svc->importForBranch($userA, $b3, sample_nfe_xml($c3, BMS_SUP, BMS_A3));
    $svc->importForBranch($userB, $bx, sample_nfe_xml($cx, BMS_SUP, BMS_B1));

    return compact('userA', 'userB', 'b1', 'b2', 'b3', 'bx', 'c1', 'c2', 'c3', 'cx');
}

function bms_api_chaves(User $user, string $query): array
{
    $token = $user->createToken('bms')->plainTextToken;
    $res = test()->getJson('/api/v1/documents?issued_from=2024-01-01&issued_to=2024-12-31&'.$query, [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    return collect($res->json('data'))->pluck('chave')->sort()->values()->all();
}

test('bms multiple branches return documents as emitter and recipient', function () {
    $w = bms_world();
    $q = 'branch_id[]='.$w['b1']->id.'&branch_id[]='.$w['b2']->id;

    expect(bms_api_chaves($w['userA'], $q))->toBe(collect([$w['c1'], $w['c2']])->sort()->values()->all());
});

test('bms scalar branch_id keeps working', function () {
    $w = bms_world();

    expect(bms_api_chaves($w['userA'], 'branch_id='.$w['b2']->id))->toBe([$w['c2']]);
});

test('bms foreign organization branch is ignored and leaks nothing', function () {
    $w = bms_world();

    // Only foreign id -> empty, never "all".
    expect(bms_api_chaves($w['userA'], 'branch_id[]='.$w['bx']->id))->toBe([]);
    // Foreign + own -> only own.
    expect(bms_api_chaves($w['userA'], 'branch_id[]='.$w['bx']->id.'&branch_id[]='.$w['b3']->id))->toBe([$w['c3']]);
});

test('bms garbage ids do not error and unknown ids yield empty result', function () {
    $w = bms_world();

    // Non-numeric junk is dropped (no filter, no 500); a well-formed but nonexistent id matches nothing.
    $all = bms_api_chaves($w['userA'], '');
    expect(bms_api_chaves($w['userA'], 'branch_id[]=abc&branch_id[]=-5&branch_id[][x]=1'))->toBe($all)
        ->and(bms_api_chaves($w['userA'], 'branch_id=abc'))->toBe($all)
        ->and(bms_api_chaves($w['userA'], 'branch_id[]=999999'))->toBe([]);
});

test('bms csv export respects branch_id array', function () {
    $w = bms_world();

    $csv = $this->actingAs($w['userA'])
        ->get(route('documents.export.csv', [
            'issued_from' => '2024-01-01', 'issued_to' => '2024-12-31',
            'branch_id' => [$w['b1']->id, $w['b2']->id],
        ]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain($w['c1'])->toContain($w['c2'])
        ->not->toContain($w['c3'])->not->toContain($w['cx']);
});

test('bms pagination and export links preserve branch_id array', function () {
    $w = bms_world();
    $url = route('documents.index', [
        'issued_from' => '2024-01-01', 'issued_to' => '2024-12-31',
        'branch_id' => [$w['b1']->id, $w['b2']->id],
    ]);

    $html = $this->actingAs($w['userA'])->get($url)->assertOk()->getContent();

    expect($html)->toContain('name="branch_id[]"')
        ->and(urldecode($html))->toContain('branch_id[0]='.$w['b1']->id)
        ->and(urldecode($html))->toContain('branch_id[1]='.$w['b2']->id);
    expect($html)->toMatch('/<option value="'.$w['b1']->id.'"\s+selected/')
        ->and($html)->not->toMatch('/<option value="'.$w['b3']->id.'"\s+selected/');
});
