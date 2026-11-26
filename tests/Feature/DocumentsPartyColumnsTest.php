<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Nfe\NfeImportService;
use App\Support\Tenant\Cnpj;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

function pc_user(string $cnpj, ?string $fantasia = null, string $social = 'pc_filial'): User
{
    $org = Organization::query()->create(['social' => 'pc_org', 'fantasia' => 'pc_org', 'cnpj' => $cnpj]);
    Branch::query()->create(['empresa' => $org->id, 'social' => $social, 'fantasia' => $fantasia, 'cnpj' => $cnpj, 'entrada' => true]);

    return User::factory()->create(['cnpj' => $cnpj, 'email' => 'pc@example.com']);
}

test('documents page shows emitter and recipient with name and formatted document, no Filial column', function () {
    $user = pc_user('11222333000181');
    FiscalDocument::query()->create(legacy_xml_row([
        'chave' => chave_for('1', '11222333000181'),
        'data_nf' => now()->toDateString(),
        'cnpj_emit' => '11222333000181', 'social_emit' => 'Fornecedor Exemplo LTDA',
        'cnpj_dest' => '12345678909', 'social_dest' => 'Maria Cliente',
    ]));
    // branch recipient with empty social_dest -> fallback to branch name
    FiscalDocument::query()->create(legacy_xml_row([
        'chave' => chave_for('2', '45543915000181'),
        'data_nf' => now()->toDateString(),
        'cnpj_emit' => '45543915000181', 'social_emit' => null,
        'cnpj_dest' => '11222333000181', 'social_dest' => '',
    ]));

    $html = $this->actingAs($user)->get('/documentos')->assertOk()
        ->assertSee('Emitente')->assertSee('Destinatário')
        ->assertSee('Fornecedor Exemplo LTDA')->assertSee('11.222.333/0001-81')
        ->assertSee('Maria Cliente')->assertSee('123.456.789-09')
        ->assertSee('pc_filial')->assertSee('45.543.915/0001-81')
        ->getContent();

    expect($html)->not->toContain('>Filial</th>');
});

test('documents page shows the issue date without time and the total without currency symbol', function () {
    $user = pc_user('11222333000181');
    FiscalDocument::query()->create(legacy_xml_row([
        'chave' => chave_for('3', '11222333000181'),
        'data_nf' => now()->toDateString(),
        'cnpj_emit' => '11222333000181',
        'total_nf' => 1234.5,
    ]));

    $html = $this->actingAs($user)->get('/documentos')->assertOk()
        ->assertSee(now()->format('d/m/Y'))
        ->assertSee('1.234,50')
        ->getContent();

    expect($html)->not->toContain(now()->format('d/m/Y').' 00:00')
        ->and($html)->not->toContain('R$ 1.234,50');
});

test('documents page branch fallback prefers fantasia', function () {
    $user = pc_user('11222333000181', 'Loja Fantasia');
    FiscalDocument::query()->create(legacy_xml_row([
        'chave' => chave_for('3', '45543915000181'),
        'data_nf' => now()->toDateString(),
        'cnpj_emit' => '45543915000181', 'cnpj_dest' => '11222333000181',
    ]));

    $this->actingAs($user)->get('/documentos')->assertOk()->assertSee('Loja Fantasia');
});

test('cnpj format handles cnpj, cpf and other lengths', function () {
    expect(Cnpj::format('45543915000181'))->toBe('45.543.915/0001-81')
        ->and(Cnpj::format('12345678909'))->toBe('123.456.789-09')
        ->and(Cnpj::format(''))->toBe('');
});

test('import stores latin1-safe social_emit and social_dest up to 100 chars', function () {
    Storage::fake('local');
    $user = pc_user('16657751000130');
    $branch = Branch::query()->firstOrFail();
    $chave = chave_for('9', '16657751000130');
    $long = str_repeat('A', 120);
    $xml = str_replace(
        ['Emitente Demonstracao LTDA', 'Destinatario Teste'],
        ['Emitente '.$long, 'Jo'."\u{4E2D}".'o Café'],
        sample_nfe_xml($chave, '16657751000130', '12345678909')
    );

    app(NfeImportService::class)->importForBranch($user, $branch, $xml);

    $doc = FiscalDocument::query()->where('chave', $chave)->firstOrFail();
    expect(mb_strlen($doc->social_emit))->toBe(100)
        ->and($doc->social_dest)->toBe('Jo?o Café');
});

test('documents page shows number/series column and the actions dropdown with every action', function () {
    $user = pc_user('11222333000181');
    $doc = FiscalDocument::query()->create(legacy_xml_row([
        'chave' => chave_for('4', '11222333000181'),
        'data_nf' => now()->toDateString(),
        'cnpj_emit' => '11222333000181',
        'numero_nf' => 351119,
        'serie_nf' => 2,
    ]));

    $html = $this->actingAs($user)->get('/documentos')->assertOk()
        ->assertSee('Número')->assertSee('351119 / 2')
        ->assertSee('aria-haspopup="menu"', false)
        ->assertSee('role="menu"', false)
        ->assertSee(route('documents.view_xml', $doc), false)
        ->assertSee(route('documents.danfe', $doc), false)
        ->assertSee(route('documents.download', $doc), false)
        ->getContent();

    expect(substr_count($html, 'role="menuitem"'))->toBeGreaterThanOrEqual(3)
        ->and($html)->toContain('delete-doc-'.$doc->id);
});

test('actions menu hides permission-gated entries', function () {
    $user = pc_user('11222333000181');
    $doc = FiscalDocument::query()->create(legacy_xml_row([
        'chave' => chave_for('5', '11222333000181'),
        'data_nf' => now()->toDateString(),
        'cnpj_emit' => '11222333000181',
    ]));
    Gate::before(fn ($u, $ability) => in_array($ability, ['delete'], true) ? false : null);

    $html = $this->actingAs($user)->get('/documentos')->assertOk()->getContent();

    expect($html)->toContain(route('documents.download', $doc))
        ->and($html)->not->toContain('id="delete-doc-'.$doc->id.'"')
        ->and($html)->not->toContain('form="delete-doc-'.$doc->id.'"');
});
