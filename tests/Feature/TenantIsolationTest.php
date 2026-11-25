<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Nfe\NfeImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const TI_CNPJ_A = '11222333000181';
const TI_CNPJ_B = '44555666000199';
const TI_CNPJ_SUPPLIER = '99888777000166';
const TI_CNPJ_STRANGER = '55444333000122';

/**
 * @return array{org: Organization, user: User, branch: Branch}
 */
function ti_tenant(string $cnpj, string $name): array
{
    $org = Organization::query()->create(['social' => $name, 'fantasia' => $name, 'cnpj' => $cnpj]);
    $user = User::factory()->create(['cnpj' => $cnpj, 'email' => strtolower($name).'@example.com']);
    $branch = Branch::query()->create([
        'empresa' => $org->id, 'social' => $name.' Matriz', 'fantasia' => null, 'cnpj' => $cnpj, 'entrada' => true,
    ]);

    return ['org' => $org, 'user' => $user, 'branch' => $branch];
}

/** Inbound doc of tenant B: external supplier -> B. */
function ti_seed_b_inbound(array $b, string $fill = '1'): array
{
    $chave = chave_for($fill, TI_CNPJ_SUPPLIER);
    $xml = sample_nfe_xml($chave, TI_CNPJ_SUPPLIER, TI_CNPJ_B);
    $doc = app(NfeImportService::class)->importForBranch($b['user'], $b['branch'], $xml)->document;

    return [$chave, $doc, $xml];
}

/** Same chave and supplier, but the XML names tenant A as recipient (forged attempt on B's doc). */
function ti_forged_for_a(string $chave): string
{
    return sample_nfe_xml($chave, TI_CNPJ_SUPPLIER, TI_CNPJ_A);
}

function ti_ingest(Branch $branch, string $xml, string $mode = 'reject')
{
    return test()->call('POST', '/api/v1/ingest/xml', [], [], [], [
        'CONTENT_TYPE' => 'application/xml',
        'HTTP_X_INGEST_TOKEN' => 'test-ingest-token',
        'HTTP_X_BRANCH_ID' => (string) $branch->id,
        'HTTP_X_ON_DUPLICATE' => $mode,
    ], $xml);
}

beforeEach(function () {
    Storage::fake('local');
});

test('web upload of xml foreign to the organization is rejected with nothing stored', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    $chave = chave_for('2', TI_CNPJ_STRANGER);
    $xml = sample_nfe_xml($chave, TI_CNPJ_STRANGER, TI_CNPJ_B);

    $this->actingAs($a['user'])
        ->post(route('import.store'), [
            'branch_id' => $a['branch']->id,
            'on_duplicate' => 'reject',
            'files' => [UploadedFile::fake()->createWithContent('foreign.xml', $xml)],
        ])
        ->assertSessionHas('import_errors_list', function ($list) {
            return count($list) === 1 && str_contains($list[0], __('nfe.import.xml_not_for_organization'));
        });

    expect(FiscalDocument::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('api and ingest reject xml foreign to the organization with 422', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    $xml = sample_nfe_xml(chave_for('2', TI_CNPJ_STRANGER), TI_CNPJ_STRANGER, TI_CNPJ_B);

    $this->actingAs($a['user'], 'sanctum')
        ->postJson('/api/v1/documents', ['branch_id' => $a['branch']->id, 'xml' => $xml])
        ->assertStatus(422)
        ->assertJsonPath('message', __('nfe.import.xml_not_for_organization'));

    ti_ingest($a['branch'], $xml)
        ->assertStatus(422)
        ->assertJsonPath('message', __('nfe.import.xml_not_for_organization'));

    expect(FiscalDocument::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('tenant A cannot replace or skip-retrieve tenant B inbound document via web', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    $b = ti_tenant(TI_CNPJ_B, 'Bravo');
    [$chave, $doc, $originalXml] = ti_seed_b_inbound($b);
    $path = $doc->storage_path;

    foreach (['replace', 'skip', 'reject'] as $mode) {
        foreach ([ti_forged_for_a($chave), $originalXml] as $xml) {
            $this->actingAs($a['user'])
                ->post(route('import.store'), [
                    'branch_id' => $a['branch']->id,
                    'on_duplicate' => $mode,
                    'files' => [UploadedFile::fake()->createWithContent('x.xml', $xml)],
                ])
                ->assertSessionHas('import_errors_list')
                ->assertSessionMissing('import_ok');
        }
    }

    $fresh = FiscalDocument::query()->where('chave', $chave)->sole();
    expect($fresh->storage_path)->toBe($path)
        ->and(ti_digits($fresh->cnpj_dest))->toBe(TI_CNPJ_B)
        ->and(Storage::disk('local')->get($path))->toBe($originalXml)
        ->and(FiscalDocument::query()->count())->toBe(1);
});

function ti_digits(?string $v): string
{
    return preg_replace('/\D/', '', (string) $v);
}

test('tenant A cannot replace or skip-retrieve tenant B inbound document via api', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    $b = ti_tenant(TI_CNPJ_B, 'Bravo');
    [$chave, $doc, $originalXml] = ti_seed_b_inbound($b);
    $path = $doc->storage_path;

    foreach (['replace', 'skip', 'reject'] as $mode) {
        // Forged XML naming A as recipient: passes the org check, hits the ownership guard.
        $this->actingAs($a['user'], 'sanctum')
            ->postJson('/api/v1/documents', [
                'branch_id' => $a['branch']->id,
                'xml' => ti_forged_for_a($chave),
                'on_duplicate' => $mode,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', __('nfe.import.duplicate_chave_other_org'))
            ->assertJsonMissingPath('id');

        // Replaying B's own XML is not A's document at all.
        $this->actingAs($a['user'], 'sanctum')
            ->postJson('/api/v1/documents', [
                'branch_id' => $a['branch']->id,
                'xml' => $originalXml,
                'on_duplicate' => $mode,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('nfe.import.xml_not_for_organization'));
    }

    $fresh = FiscalDocument::query()->where('chave', $chave)->sole();
    expect($fresh->storage_path)->toBe($path)
        ->and(ti_digits($fresh->cnpj_dest))->toBe(TI_CNPJ_B)
        ->and(Storage::disk('local')->get($path))->toBe($originalXml)
        ->and(Storage::disk('local')->allFiles())->toBe([$path]);
});

test('ingest for tenant A cannot replace or skip-retrieve tenant B inbound document', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    $b = ti_tenant(TI_CNPJ_B, 'Bravo');
    [$chave, $doc, $originalXml] = ti_seed_b_inbound($b);
    $path = $doc->storage_path;

    foreach (['replace', 'skip', 'reject'] as $mode) {
        ti_ingest($a['branch'], ti_forged_for_a($chave), $mode)
            ->assertStatus(409)
            ->assertJsonPath('message', __('nfe.import.duplicate_chave_other_org'))
            ->assertJsonMissingPath('id');

        ti_ingest($a['branch'], $originalXml, $mode)
            ->assertStatus(422)
            ->assertJsonPath('message', __('nfe.import.xml_not_for_organization'));
    }

    $fresh = FiscalDocument::query()->where('chave', $chave)->sole();
    expect($fresh->storage_path)->toBe($path)
        ->and(ti_digits($fresh->cnpj_dest))->toBe(TI_CNPJ_B)
        ->and(Storage::disk('local')->get($path))->toBe($originalXml)
        ->and(Storage::disk('local')->allFiles())->toBe([$path]);
});

test('chave whose embedded emitter differs from the xml emitter is rejected', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    // chave embeds the stranger's CNPJ, XML says the emitter is A.
    $xml = sample_nfe_xml(chave_for('3', TI_CNPJ_STRANGER), TI_CNPJ_A, TI_CNPJ_B);

    $this->actingAs($a['user'], 'sanctum')
        ->postJson('/api/v1/documents', ['branch_id' => $a['branch']->id, 'xml' => $xml])
        ->assertStatus(422)
        ->assertJsonPath('message', __('nfe.import.chave_emitter_mismatch'));

    ti_ingest($a['branch'], $xml)
        ->assertStatus(422)
        ->assertJsonPath('message', __('nfe.import.chave_emitter_mismatch'));

    $this->actingAs($a['user'])
        ->post(route('import.store'), [
            'branch_id' => $a['branch']->id,
            'on_duplicate' => 'reject',
            'files' => [UploadedFile::fake()->createWithContent('m.xml', $xml)],
        ])
        ->assertSessionHas('import_errors_list', fn ($l) => str_contains($l[0], __('nfe.import.chave_emitter_mismatch')));

    expect(FiscalDocument::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('cpf emitter chave is matched zero padded', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    $cpf = '12345678901';
    $chave = chave_for('4', $cpf);
    $xml = sample_nfe_xml($chave, $cpf, TI_CNPJ_A);

    $this->actingAs($a['user'], 'sanctum')
        ->postJson('/api/v1/documents', ['branch_id' => $a['branch']->id, 'xml' => $xml])
        ->assertCreated();
});

test('tenant B can import and replace its own inbound document', function () {
    $b = ti_tenant(TI_CNPJ_B, 'Bravo');
    $chave = chave_for('5', TI_CNPJ_SUPPLIER);
    $xml = sample_nfe_xml($chave, TI_CNPJ_SUPPLIER, TI_CNPJ_B);

    $this->actingAs($b['user'], 'sanctum')
        ->postJson('/api/v1/documents', ['branch_id' => $b['branch']->id, 'xml' => $xml])
        ->assertCreated()
        ->assertJsonPath('result', 'created');

    $this->actingAs($b['user'], 'sanctum')
        ->postJson('/api/v1/documents', ['branch_id' => $b['branch']->id, 'xml' => $xml, 'on_duplicate' => 'skip'])
        ->assertOk()
        ->assertJsonPath('result', 'skipped');

    $this->actingAs($b['user'], 'sanctum')
        ->postJson('/api/v1/documents', ['branch_id' => $b['branch']->id, 'xml' => $xml, 'on_duplicate' => 'replace'])
        ->assertOk()
        ->assertJsonPath('result', 'replaced');

    ti_ingest($b['branch'], $xml, 'replace')->assertOk()->assertJsonPath('result', 'replaced');

    expect(FiscalDocument::query()->where('chave', $chave)->count())->toBe(1);
});

test('tenant A cannot see or touch tenant B document', function () {
    $a = ti_tenant(TI_CNPJ_A, 'Alpha');
    $b = ti_tenant(TI_CNPJ_B, 'Bravo');
    [$chave, $doc, $originalXml] = ti_seed_b_inbound($b);

    $this->actingAs($b['user'])->get(route('documents.view_xml', $doc))->assertOk();

    $this->actingAs($a['user'])->get(route('documents.index'))
        ->assertOk()
        ->assertDontSee($chave);

    $this->actingAs($a['user'])->get(route('documents.view_xml', $doc))->assertForbidden();
    $this->actingAs($a['user'])->get(route('documents.download', $doc))->assertForbidden();
    $this->actingAs($a['user'])->get(route('documents.danfe', $doc))->assertForbidden();
    $this->actingAs($a['user'])->delete(route('documents.destroy', $doc))->assertForbidden();

    $this->actingAs($a['user'], 'sanctum')->getJson("/api/v1/documents/{$doc->id}")->assertForbidden();
    $this->actingAs($a['user'], 'sanctum')->getJson("/api/v1/documents/{$doc->id}/danfe")->assertForbidden();

    $listed = $this->actingAs($a['user'], 'sanctum')->getJson('/api/v1/documents?per_page=50')->assertOk();
    expect(collect($listed->json('data'))->pluck('chave')->all())->not->toContain($chave);

    expect(FiscalDocument::query()->whereKey($doc->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists($doc->storage_path))->toBeTrue();
});

test('xml addressed only to the matriz cnpj without a matching branch is rejected', function () {
    $org = Organization::query()->create(['social' => 'Solo', 'fantasia' => 'Solo', 'cnpj' => TI_CNPJ_A]);
    $user = User::factory()->create(['cnpj' => TI_CNPJ_A, 'email' => 'solo@example.com']);
    $branch = Branch::query()->create([
        'empresa' => $org->id, 'social' => 'Filial', 'fantasia' => null, 'cnpj' => '77666555000144', 'entrada' => true,
    ]);
    $xml = sample_nfe_xml(chave_for('6', TI_CNPJ_SUPPLIER), TI_CNPJ_SUPPLIER, TI_CNPJ_A);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/documents', ['branch_id' => $branch->id, 'xml' => $xml])
        ->assertStatus(422)
        ->assertJsonPath('message', __('nfe.import.xml_not_for_organization'));

    expect(FiscalDocument::query()->count())->toBe(0);
});
