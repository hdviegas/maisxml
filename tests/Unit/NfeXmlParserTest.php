<?php

use App\Services\Nfe\NfeXmlParser;

test('parser extracts chave and totals from nfe xml', function () {
    $chave = str_repeat('3', 44);
    $parser = new NfeXmlParser;
    $data = $parser->parse(sample_nfe_xml($chave));

    expect($data->chave)->toBe($chave)
        ->and($data->modelo)->toBe('55')
        ->and($data->serie)->toBe(1)
        ->and($data->numero)->toBe(99)
        ->and($data->cfop)->toBe('5102')
        ->and($data->emitterCnpj)->toBe('16657751000130')
        ->and($data->recipientDoc)->toBe('12345678901')
        ->and($data->total)->toBe('123.45');
});

test('parser extracts chave and modelo from nfce xml', function () {
    $chave = str_repeat('2', 44);
    $parser = new NfeXmlParser;
    $data = $parser->parse(sample_nfce_xml($chave));

    expect($data->chave)->toBe($chave)
        ->and($data->modelo)->toBe('65')
        ->and($data->total)->toBe('10.00');
});

test('parser rejects empty xml', function () {
    $parser = new NfeXmlParser;
    $parser->parse('');
})->throws(InvalidArgumentException::class);

test('parser extracts emitter name, icms total and freight from ICMSTot', function () {
    $nfe = (new NfeXmlParser)->parse(sample_nfe_xml(str_repeat('3', 44)));
    $nfce = (new NfeXmlParser)->parse(sample_nfce_xml(str_repeat('2', 44)));

    expect($nfe->emitterName)->toBe('Emitente Demonstracao LTDA')
        ->and($nfe->totalIcms)->toBe('18.00')
        ->and($nfe->totalFrete)->toBe('0.00')
        ->and($nfce->emitterName)->toBe('Emitente NFC-e Teste')
        ->and($nfce->totalIcms)->toBe('1.80')
        ->and($nfce->recipientDoc)->toBeNull();
});
