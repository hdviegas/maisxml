<?php

namespace App\Services\Sped;

use Carbon\Carbon;

class SpedC100Parser
{
    /**
     * @return \Generator<int, array{chave_nf: ?string, data_nf: ?Carbon, cod_mod: ?string, serie: ?string, numero_doc: ?string, total_nf: ?string, total_icms: ?string, raw_line: string}>
     */
    public function lines(string $content): \Generator
    {
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line);
            $pos = false;
            foreach ($parts as $i => $p) {
                if ($p === 'C100') {
                    $pos = $i;
                    break;
                }
            }
            if ($pos === false) {
                continue;
            }

            $codMod = $parts[$pos + 4] ?? null;
            $serie = $parts[$pos + 6] ?? null;
            $numDoc = $parts[$pos + 7] ?? null;
            $chv = $parts[$pos + 8] ?? null;
            $dtDoc = $parts[$pos + 9] ?? null;
            $vlDoc = $parts[$pos + 11] ?? null;
            $vlIcms = $parts[$pos + 20] ?? null;

            $chave = $chv !== null && strlen(preg_replace('/\D/', '', $chv) ?: '') === 44
                ? preg_replace('/\D/', '', $chv)
                : (strlen((string) $chv) === 44 && ctype_digit((string) $chv) ? $chv : null);

            $dataNf = null;
            if ($dtDoc !== null && $dtDoc !== '' && preg_match('/^\d{8}$/', $dtDoc)) {
                try {
                    $dataNf = Carbon::createFromFormat('dmY', $dtDoc)->startOfDay();
                } catch (\Throwable) {
                    $dataNf = null;
                }
            }

            yield [
                'chave_nf' => $chave,
                'data_nf' => $dataNf,
                'cod_mod' => $codMod !== '' ? $codMod : null,
                'serie' => $serie !== '' ? $serie : null,
                'numero_doc' => $numDoc !== '' ? $numDoc : null,
                'total_nf' => $this->normDecimal($vlDoc),
                'total_icms' => $this->normDecimal($vlIcms),
                'raw_line' => strlen($line) > 65000 ? substr($line, 0, 65000) : $line,
            ];
        }
    }

    private function normDecimal(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = preg_replace('/[^\d,.-]/', '', trim($value)) ?? '';
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? $v : null;
    }
}
