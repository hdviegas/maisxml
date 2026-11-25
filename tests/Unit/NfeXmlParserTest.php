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
