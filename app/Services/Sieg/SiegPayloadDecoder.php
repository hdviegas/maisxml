<?php

namespace App\Services\Sieg;

use App\Services\Sieg\Exceptions\SiegException;
use ZipArchive;

/**
 * Extrai XMLs de uma resposta cujo formato não é documentado pela SIEG:
 * string XML, Base64 de XML, Base64 de ZIP, ou JSON contendo qualquer um deles.
 */
class SiegPayloadDecoder
{
    private const IGNORED_KEYS = ['status', 'codigo', 'mensagens'];

    private const PRIORITY_KEYS = ['xmls', 'xml', 'arquivo', 'arquivos', 'zip', 'zipbase64', 'base64', 'conteudo', 'data', 'documentos'];

    private const MAX_DEPTH = 10;

    public int $maxBytes = 200 * 1024 * 1024;

    public int $maxEntries = 5000;

    /**
     * @return list<SiegDocument>
     */
    public function decode(mixed $body): array
    {
        $found = [];
        $state = ['bytes' => 0, 'entries' => 0];
        $this->walk($body, $found, $state, 0, 0);

        $unique = [];
        foreach ($found as $doc) {
            $unique[hash('sha256', $doc->xml)] ??= $doc;
        }

        return array_values($unique);
    }

    /**
     * @param  list<SiegDocument>  $out
     * @param  array{bytes:int,entries:int}  $state
     */
    private function walk(mixed $value, array &$out, array &$state, int $zipDepth, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_string($value)) {
            $this->handleString($value, $out, $state, $zipDepth);

            return;
        }
        if (! is_array($value)) {
            return;
        }

        if (array_is_list($value)) {
            foreach ($value as $item) {
                $this->walk($item, $out, $state, $zipDepth, $depth + 1);
            }

            return;
        }

        $priority = [];
        $others = [];
        foreach ($value as $key => $item) {
            $lower = strtolower((string) $key);
            if (in_array($lower, self::IGNORED_KEYS, true)) {
                continue;
            }
            if (in_array($lower, self::PRIORITY_KEYS, true)) {
                $priority[] = $item;
            } else {
                $others[] = $item;
            }
        }
        foreach ([...$priority, ...$others] as $item) {
            if (is_string($item) || is_array($item) || is_object($item)) {
                $this->walk($item, $out, $state, $zipDepth, $depth + 1);
            }
        }
    }

    /**
     * @param  list<SiegDocument>  $out
     * @param  array{bytes:int,entries:int}  $state
     */
    private function handleString(string $raw, array &$out, array &$state, int $zipDepth): void
    {
        $s = trim($raw, " \t\r\n\"'");
        if ($s === '') {
            return;
        }

        $xml = $this->asXml($s);
        if ($xml !== null) {
            $this->account($state, strlen($xml), 1);
            $out[] = new SiegDocument($xml);

            return;
        }

        $compact = preg_replace('/\s+/', '', $s);
        $bytes = base64_decode($compact, true);
        if ($bytes === false || $bytes === '') {
            return;
        }

        if (str_starts_with($bytes, 'PK')) {
            if ($zipDepth < 2) {
                $this->unzip($bytes, $out, $state, $zipDepth + 1);
            }

            return;
        }

        $xml = $this->asXml($bytes);
        if ($xml !== null) {
            $this->account($state, strlen($xml), 1);
            $out[] = new SiegDocument($xml);
        }
    }

    private function asXml(string $s): ?string
    {
        $s = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $s));

        return str_starts_with($s, '<') ? $s : null;
    }

    /**
     * @param  list<SiegDocument>  $out
     * @param  array{bytes:int,entries:int}  $state
     */
    private function unzip(string $bytes, array &$out, array &$state, int $zipDepth): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sieg');
        if ($tmp === false) {
            throw new SiegException('Não foi possível criar arquivo temporário para o ZIP.');
        }

        try {
            file_put_contents($tmp, $bytes);
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                return;
            }

            try {
                if ($zip->numFiles > $this->maxEntries) {
                    throw new SiegException('ZIP com entradas demais (proteção contra zip bomb).');
                }

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if ($stat === false || str_ends_with($stat['name'], '/')) {
                        continue;
                    }
                    $this->account($state, (int) $stat['size'], 1);

                    $content = $zip->getFromIndex($i);
                    if ($content === false) {
                        continue;
                    }
                    $name = basename($stat['name']);

                    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xml') {
                        $xml = $this->asXml($content);
                        if ($xml !== null) {
                            $out[] = new SiegDocument($xml, $name);
                        }
                    } elseif (str_starts_with($content, 'PK') && $zipDepth < 2) {
                        $this->unzip($content, $out, $state, $zipDepth + 1);
                    }
                }
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  array{bytes:int,entries:int}  $state
     */
    private function account(array &$state, int $bytes, int $entries): void
    {
        $state['bytes'] += $bytes;
        $state['entries'] += $entries;
        if ($state['bytes'] > $this->maxBytes || $state['entries'] > $this->maxEntries) {
            throw new SiegException('Resposta excede os limites de tamanho/entradas (proteção contra zip bomb).');
        }
    }
}
