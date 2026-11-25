<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('api returns danfe pdf with bearer token', function () {
    $this->seed();

    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();
    Branch::query()->firstOrFail();
    $token = $user->createToken('pest')->plainTextToken;
    $chave = str_repeat('1', 44);

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

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/pdf',
    ])->get("/api/v1/documents/{$doc->id}/danfe");

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect(mb_substr($response->getContent(), 0, 4))->toBe('%PDF');
});
