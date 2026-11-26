<?php

use App\Services\Sieg\Exceptions\SiegException;
use App\Services\Sieg\SiegPayloadDecoder;

function sieg_zip(array $files): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'tz');
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    $bytes = file_get_contents($tmp);
    unlink($tmp);

    return $bytes;
}

const SIEGD_XML_A = '<?xml version="1.0"?><nfeProc><a>1</a></nfeProc>';
const SIEGD_XML_B = '<?xml version="1.0"?><nfeProc><b>2</b></nfeProc>';

it('decodes a base64 zip with two XMLs keeping filenames', function () {
    $b64 = base64_encode(sieg_zip(['a.xml' => SIEGD_XML_A, 'b.xml' => SIEGD_XML_B]));
    $docs = (new SiegPayloadDecoder)->decode($b64);

    expect($docs)->toHaveCount(2)
        ->and($docs[0]->xml)->toBe(SIEGD_XML_A)
        ->and($docs[0]->filename)->toBe('a.xml');
});

it('decodes quoted base64 strings and raw XML', function () {
    $d = new SiegPayloadDecoder;
    expect($d->decode(SIEGD_XML_A))->toHaveCount(1)
        ->and($d->decode('"'.base64_encode(SIEGD_XML_B).'"')[0]->xml)->toBe(SIEGD_XML_B)
        ->and($d->decode("\xEF\xBB\xBF".SIEGD_XML_A)[0]->xml)->toBe(SIEGD_XML_A);
});

it('decodes JSON with xmls list', function () {
    $docs = (new SiegPayloadDecoder)->decode(['xmls' => [base64_encode(SIEGD_XML_A), base64_encode(SIEGD_XML_B)]]);
    expect($docs)->toHaveCount(2);
});

it('decodes Status/Arquivo envelope ignoring status fields', function () {
    $docs = (new SiegPayloadDecoder)->decode([
        'Status' => 'OK', 'Codigo' => 200, 'Mensagens' => ['<x>ignored</x>'],
        'Arquivo' => base64_encode(sieg_zip(['n.xml' => SIEGD_XML_A])),
    ]);
    expect($docs)->toHaveCount(1)->and($docs[0]->xml)->toBe(SIEGD_XML_A);
});

it('handles nested zips and deduplicates', function () {
    $inner = sieg_zip(['in.xml' => SIEGD_XML_A]);
    $outer = base64_encode(sieg_zip(['inner.zip' => $inner, 'dup.xml' => SIEGD_XML_A, 'other.xml' => SIEGD_XML_B]));
    $docs = (new SiegPayloadDecoder)->decode(['data' => ['Zip' => $outer]]);

    expect($docs)->toHaveCount(2);
});

it('ignores non-xml zip entries and non-xml strings', function () {
    $b64 = base64_encode(sieg_zip(['readme.txt' => 'hello', 'a.xml' => SIEGD_XML_A]));
    $docs = (new SiegPayloadDecoder)->decode([$b64, 'OK', 'not base64 !!!', 42, null]);

    expect($docs)->toHaveCount(1);
});

it('guards against zip bombs by entries and size', function () {
    $files = [];
    for ($i = 0; $i < 5001; $i++) {
        $files["f$i.xml"] = '<a/>';
    }
    $b64 = base64_encode(sieg_zip($files));
    expect(fn () => (new SiegPayloadDecoder)->decode($b64))->toThrow(SiegException::class);

    $small = new SiegPayloadDecoder;
    $small->maxBytes = 100;
    $big = base64_encode(sieg_zip(['big.xml' => '<a>'.str_repeat('x', 500).'</a>']));
    expect(fn () => $small->decode($big))->toThrow(SiegException::class);
});

it('decodes a raw binary zip with two XMLs keeping entry names', function () {
    $raw = sieg_zip(['a.xml' => SIEGD_XML_A, 'b.xml' => SIEGD_XML_B]);
    expect(str_starts_with($raw, "PK\x03\x04"))->toBeTrue();

    $docs = (new SiegPayloadDecoder)->decode($raw);

    expect($docs)->toHaveCount(2)
        ->and($docs[0]->filename)->toBe('a.xml')
        ->and($docs[1]->filename)->toBe('b.xml')
        ->and($docs[1]->xml)->toBe(SIEGD_XML_B);
});

it('decodes a raw zip nested in another raw zip', function () {
    $inner = sieg_zip(['in.xml' => SIEGD_XML_A]);
    $outer = sieg_zip(['inner.zip' => $inner, 'o.xml' => SIEGD_XML_B]);

    expect((new SiegPayloadDecoder)->decode($outer))->toHaveCount(2);
});

it('throws on unrecognized binary garbage instead of silently returning nothing', function () {
    expect(fn () => (new SiegPayloadDecoder)->decode("\x00\x01\x02garbage\xff\xfe"))
        ->toThrow(SiegException::class, 'Formato de resposta da SIEG não reconhecido');
});

it('returns an empty list for empty strings, empty zips and empty JSON', function () {
    $d = new SiegPayloadDecoder;
    expect($d->decode(''))->toBe([])
        ->and($d->decode("  \n"))->toBe([])
        ->and($d->decode(sieg_zip(['readme.txt' => 'hello'])))->toBe([])
        ->and($d->decode(['Status' => 'OK', 'xmls' => []]))->toBe([]);
});
