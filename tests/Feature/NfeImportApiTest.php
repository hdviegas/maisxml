<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\User;

test('api v1 creates fiscal document with bearer token', function () {
    $this->seed();

    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $branch = Branch::query()->firstOrFail();

    $token = $user->createToken('pest')->plainTextToken;
    $chave = chave_for('7');
    $xml = sample_nfe_xml($chave);

    $response = $this->postJson('/api/v1/documents', [
        'branch_id' => $branch->id,
        'xml' => $xml,
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertCreated()
        ->assertJsonPath('chave', $chave)
        ->assertJsonPath('result', 'created');

    expect(FiscalDocument::query()->where('chave', $chave)->exists())->toBeTrue();
});

test('api v1 skip duplicate returns 200 with skipped result', function () {
    $this->seed();

    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $branch = Branch::query()->firstOrFail();

    $token = $user->createToken('pest')->plainTextToken;
    $chave = chave_for('9');
    $xml = sample_nfe_xml($chave);

    $this->postJson('/api/v1/documents', [
        'branch_id' => $branch->id,
        'xml' => $xml,
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertCreated();

    $response = $this->postJson('/api/v1/documents', [
        'branch_id' => $branch->id,
        'xml' => $xml,
        'on_duplicate' => 'skip',
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJsonPath('chave', $chave)
        ->assertJsonPath('result', 'skipped');

    expect(FiscalDocument::query()->where('chave', $chave)->count())->toBe(1);
});

test('api rejects duplicate chave', function () {
    $this->seed();

    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $branch = Branch::query()->firstOrFail();
    $token = $user->createToken('pest')->plainTextToken;
    $chave = chave_for('8');
    $xml = sample_nfe_xml($chave);

    $this->postJson('/api/v1/documents', ['branch_id' => $branch->id, 'xml' => $xml], [
        'Authorization' => 'Bearer '.$token,
    ])->assertCreated();

    $this->postJson('/api/v1/documents', ['branch_id' => $branch->id, 'xml' => $xml], [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(409);
});

test('authenticated user sees documents page', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk();
});
