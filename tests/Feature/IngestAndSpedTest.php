<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Sped;
use App\Models\User;
use App\Services\Sped\SpedC100ImportService;

test('ingest xml with token creates fiscal document', function () {
    $this->seed();
    $branch = Branch::query()->firstOrFail();
    $chave = chave_for('6');
    $xml = sample_nfe_xml($chave);

    $response = $this->call(
        'POST',
        '/api/v1/ingest/xml',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/xml',
            'HTTP_X_INGEST_TOKEN' => 'test-ingest-token',
            'HTTP_X_BRANCH_ID' => (string) $branch->id,
        ],
        $xml,
    );

    $response->assertCreated()
        ->assertJsonPath('chave', $chave)
        ->assertJsonPath('result', 'created');
});

test('ingest rejects invalid token', function () {
    $this->seed();
    $branch = Branch::query()->firstOrFail();
    $xml = sample_nfe_xml(chave_for('6'));

    $this->call(
        'POST',
        '/api/v1/ingest/xml',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/xml',
            'HTTP_X_INGEST_TOKEN' => 'wrong-token',
            'HTTP_X_BRANCH_ID' => (string) $branch->id,
        ],
        $xml,
    )->assertUnauthorized();
});

test('sped c100 links to fiscal document by chave', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $branch = Branch::query()->firstOrFail();
    $token = $user->createToken('p')->plainTextToken;
    $chave = chave_for('5');
    $this->postJson('/api/v1/documents', [
        'branch_id' => $branch->id,
        'xml' => sample_nfe_xml($chave),
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    $line = "|C100|0|1|PART|55|00|1|999|{$chave}|01062024|01062024|1234,56|||||||||99,99|";
    app(SpedC100ImportService::class)->importFromText($user, $branch, $line);

    FiscalDocument::query()->where('chave', $chave)->firstOrFail();

    expect(
        Sped::query()->where('chave_nf', $chave)->exists(),
    )->toBeTrue();
});
