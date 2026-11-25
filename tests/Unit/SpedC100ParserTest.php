<?php

use App\Services\Sped\SpedC100Parser;

test('sped c100 parser extracts chave and values', function () {
    $chave = str_repeat('4', 44);
    $line = "|C100|0|1|PART|55|00|1|999|{$chave}|01062024|01062024|1234,56|||||||||99,99|";

    $parser = new SpedC100Parser;
    $rows = iterator_to_array($parser->lines($line));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['chave_nf'])->toBe($chave)
        ->and($rows[0]['cod_mod'])->toBe('55')
        ->and($rows[0]['numero_doc'])->toBe('999')
        ->and($rows[0]['total_nf'])->toBe('1234.56')
        ->and($rows[0]['total_icms'])->toBe('99.99');
});
