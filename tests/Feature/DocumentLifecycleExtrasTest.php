<?php

use App\Models\FiscalDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('lookup by chave redirects to document list with query', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $chave = str_repeat('4', 44);

    $emitter = '16657751000130';
    $issued = now()->format('m-y');
    $path = 'xml/'.$emitter.'/'.$issued.'/'.$chave.'.xml';

    Storage::disk('local')->put($path, sample_nfe_xml($chave));

    FiscalDocument::query()->create([
        'url' => $path,
        'chave' => $chave,
        'empresa' => $emitter,
        'social' => null,
        'dest' => null,
        'data_nf' => now(),
        'ip' => '127.0.0.1',
        'cnpj_emit' => $emitter,
        'cnpj_dest' => '12345678901',
        'social_emit' => null,
        'social_dest' => null,
        'numero_nf' => 99,
        'modelo_nf' => '55',
        'serie_nf' => '1',
        'total_nf' => '123.45',
        'total_icms' => null,
        'total_icms_st' => null,
        'total_frete' => null,
        'cfop_nf' => '5102',
        'cancelada' => false,
        'sped' => null,
    ]);

    $this->actingAs($user)
        ->get(route('documents.lookup_chave', $chave))
        ->assertRedirect(route('documents.index', ['q' => $chave], absolute: false));
});

test('user can delete fiscal document and xml is removed', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    $chave = str_repeat('5', 44);

    $emitter = '16657751000130';
    $issued = now()->format('m-y');
    $path = 'xml/'.$emitter.'/'.$issued.'/'.$chave.'.xml';

    Storage::disk('local')->put($path, sample_nfe_xml($chave));

    $doc = FiscalDocument::query()->create([
        'url' => $path,
        'chave' => $chave,
        'empresa' => $emitter,
        'social' => null,
        'dest' => null,
        'data_nf' => now(),
        'ip' => '127.0.0.1',
        'cnpj_emit' => $emitter,
        'cnpj_dest' => '12345678901',
        'social_emit' => null,
        'social_dest' => null,
        'numero_nf' => 99,
        'modelo_nf' => '55',
        'serie_nf' => '1',
        'total_nf' => '123.45',
        'total_icms' => null,
        'total_icms_st' => null,
        'total_frete' => null,
        'cfop_nf' => '5102',
        'cancelada' => false,
        'sped' => null,
    ]);

    $this->actingAs($user)
        ->delete(route('documents.destroy', $doc))
        ->assertRedirect(route('documents.index'));

    expect(FiscalDocument::query()->find($doc->id))->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

test('settings page shows organizations', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Empresa demonstração', escape: false);
});

test('manifestacao page loads', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('manifestacao.index'))
        ->assertOk()
        ->assertSee(__('manifestacao.title'), escape: false);
});
