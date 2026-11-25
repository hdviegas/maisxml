<?php

use App\Enums\DocumentOperationType;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Sped\SpedC100ImportService;

test('api import sets operation_type from cfop 5102', function () {
    $this->seed();

    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $branch = Branch::query()->firstOrFail();
    $token = $user->createToken('pest')->plainTextToken;
    $chave = chave_for('9');
    $xml = sample_nfe_xml($chave);

    $this->postJson('/api/v1/documents', [
        'branch_id' => $branch->id,
        'xml' => $xml,
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    $doc = FiscalDocument::query()->where('chave', $chave)->firstOrFail();
    expect($doc->operation_type)->toBe(DocumentOperationType::Saida);
});

test('api document index returns sped reconcile diff when totals mismatch', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $branch = Branch::query()->firstOrFail();
    $token = $user->createToken('pest')->plainTextToken;
    $chave = chave_for('4');

    $this->postJson('/api/v1/documents', [
        'branch_id' => $branch->id,
        'xml' => sample_nfe_xml($chave),
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    $line = "|C100|0|1|PART|55|00|1|999|{$chave}|01062024|01062024|9999,99|||||||||99,99|";
    app(SpedC100ImportService::class)->importFromText($user, $branch, $line);

    $response = $this->getJson('/api/v1/documents?per_page=10', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk();
    $rows = collect($response->json('data'));
    $row = $rows->firstWhere('chave', $chave);
    expect($row)->not->toBeNull()
        ->and($row['sped_matched'])->toBeTrue()
        ->and($row['sped_reconcile'])->toBe('diff');
});

test('authenticated user can download csv export', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('documents.export.csv'))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv');
});

test('csv export records activity log with organization', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $orgId = Organization::query()->forUser($user)->firstOrFail()->id;

    $this->actingAs($user)
        ->get(route('documents.export.csv'))
        ->assertOk();

    $log = ActivityLog::query()->where('action', 'documents.export_csv')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->organization_id)->toBe($orgId);
});

test('dashboard shows scoped stats', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.stat_documents'), escape: false);
});

test('dashboard respects issued_from and issued_to for the period form', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('dashboard', [
            'issued_from' => '2020-01-01',
            'issued_to' => '2020-01-31',
        ]))
        ->assertOk()
        ->assertSee('value="2020-01-01"', escape: false)
        ->assertSee('value="2020-01-31"', escape: false);
});

test('import writes activity log row', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $branch = Branch::query()->firstOrFail();
    $token = $user->createToken('pest')->plainTextToken;
    $chave = chave_for('3');

    $this->postJson('/api/v1/documents', [
        'branch_id' => $branch->id,
        'xml' => sample_nfe_xml($chave),
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    expect(
        ActivityLog::query()->where('action', 'fiscal_document.imported')->exists()
    )->toBeTrue();
});
