<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('authenticated user can open danfe pdf for nf-e modelo 55', function () {
    $this->seed();

    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    Branch::query()->firstOrFail();
    $chave = str_repeat('7', 44);

    $emitter = '16657751000130';
    $issued = now()->format('m-y');
    $path = 'xml/'.$emitter.'/'.$issued.'/'.$chave.'.xml';

    Storage::disk('local')->put($path, sample_nfe_xml($chave));

    $doc = FiscalDocument::query()->create([
        'url' => $path,
        'chave' => $chave,
        'empresa' => $emitter,
        'social' => '',
        'dest' => '', 'tipo' => '',
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
        'total_icms' => 0,
        'total_icms_st' => null,
        'total_frete' => 0,
        'cfop_nf' => '5102',
        'cancelada' => false,
        'sped' => null,
    ]);

    $response = $this->actingAs($user)->get(route('documents.danfe', $doc));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect(mb_substr($response->getContent(), 0, 4))->toBe('%PDF');
});
