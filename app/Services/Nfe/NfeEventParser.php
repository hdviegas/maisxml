<?php

namespace App\Services\Nfe;

use Carbon\Carbon;
use DOMDocument;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;

class NfeEventParser
{
    /**
     * Accepts procEventoNFe or a bare evento element (any namespace).
     *
     * @throws InvalidArgumentException not_an_event | invalid_event | empty_xml | invalid_xml
     */
    public function parse(string $xml): NfeEventData
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new InvalidArgumentException('empty_xml');
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            throw new InvalidArgumentException('invalid_xml');
        }

        $xpath = new DOMXPath($dom);
        // The event itself: infEvento that is not inside retEvento.
        $inf = $xpath->query('//*[local-name() = \'evento\']/*[local-name() = \'infEvento\']')->item(0)
            ?? $xpath->query('//*[local-name() = \'infEvento\'][not(ancestor::*[local-name() = \'retEvento\'])]')->item(0);
        if ($inf === null || $this->text($xpath, './*[local-name() = \'chNFe\']', $inf) === null) {
            throw new InvalidArgumentException('not_an_event');
        }

        $chave = $this->text($xpath, './*[local-name() = \'chNFe\']', $inf);
        if (strlen($chave) !== 44 || ! ctype_digit($chave)) {
            throw new InvalidArgumentException('invalid_event');
        }

        $tp = $this->text($xpath, './*[local-name() = \'tpEvento\']', $inf);
        if ($tp === null || ! ctype_digit($tp)) {
            throw new InvalidArgumentException('invalid_event');
        }
        $seq = $this->text($xpath, './*[local-name() = \'nSeqEvento\']', $inf);

        $dh = null;
        $dhRaw = $this->text($xpath, './*[local-name() = \'dhEvento\']', $inf);
        if ($dhRaw !== null) {
            try {
                $dh = Carbon::parse($dhRaw);
            } catch (\Throwable) {
                $dh = null;
            }
        }

        $author = $this->text($xpath, './*[local-name() = \'CNPJ\']', $inf)
            ?? $this->text($xpath, './*[local-name() = \'CPF\']', $inf);

        $det = $xpath->query('./*[local-name() = \'detEvento\']', $inf)->item(0);

        $ret = $xpath->query('//*[local-name() = \'retEvento\']/*[local-name() = \'infEvento\']')->item(0);

        return new NfeEventData(
            chave: $chave,
            tpEvento: (int) $tp,
            nSeqEvento: $seq !== null && ctype_digit($seq) ? (int) $seq : 1,
            dhEvento: $dh,
            authorDoc: $author,
            descricao: $det ? $this->text($xpath, './*[local-name() = \'descEvento\']', $det) : null,
            xCorrecao: $det ? $this->text($xpath, './*[local-name() = \'xCorrecao\']', $det) : null,
            cStat: $ret ? $this->text($xpath, './*[local-name() = \'cStat\']', $ret) : null,
            protocolo: $ret ? $this->text($xpath, './*[local-name() = \'nProt\']', $ret) : null,
        );
    }

    private function text(DOMXPath $xpath, string $query, DOMNode $context): ?string
    {
        $n = $xpath->query($query, $context)->item(0);
        $t = $n !== null ? trim($n->textContent) : '';

        return $t !== '' ? $t : null;
    }
}
