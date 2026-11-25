<?php

namespace App\Services\Nfe;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NfeXmlParser
{
    public function parse(string $xml): NfeParsedData
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
        $infList = $xpath->query('//*[local-name() = \'infNFe\']');
        if ($infList->length === 0) {
            throw new InvalidArgumentException('missing_infnfe');
        }

        /** @var \DOMElement $inf */
        $inf = $infList->item(0);
        $id = $inf->getAttribute('Id');
        if ($id === '') {
            throw new InvalidArgumentException('missing_id');
        }

        $chave = Str::startsWith($id, 'NFe') ? substr($id, 3) : $id;
        if (strlen($chave) !== 44 || ! ctype_digit($chave)) {
            throw new InvalidArgumentException('invalid_chave');
        }

        $ide = $xpath->query('.//*[local-name() = \'ide\']', $inf)->item(0);
        $ctxIde = $ide ?? $inf;
        $mod = $this->firstText($xpath, './/*[local-name() = \'mod\']', $ctxIde)
            ?? $this->firstText($xpath, './/*[local-name() = \'cMod\']', $ctxIde);
        $serie = $this->firstInt($xpath, './/*[local-name() = \'serie\']', $ctxIde);
        $numero = $this->firstInt($xpath, './/*[local-name() = \'nNF\']', $ctxIde);
        $dhEmi = $this->firstText($xpath, './/*[local-name() = \'dhEmi\']', $ctxIde);
        $dEmi = $this->firstText($xpath, './/*[local-name() = \'dEmi\']', $ctxIde);

        $issuedAt = null;
        if ($dhEmi) {
            try {
                $issuedAt = Carbon::parse($dhEmi);
            } catch (\Throwable) {
                $issuedAt = null;
            }
        }
        if ($issuedAt === null && $dEmi) {
            try {
                $issuedAt = Carbon::createFromFormat('Y-m-d', $dEmi)->startOfDay();
            } catch (\Throwable) {
                $issuedAt = null;
            }
        }

        $emit = $xpath->query('.//*[local-name() = \'emit\']', $inf)->item(0);
        $emitterCnpj = $this->firstText($xpath, './/*[local-name() = \'CNPJ\']', $emit)
            ?? $this->firstText($xpath, './/*[local-name() = \'CPF\']', $emit);

        $dest = $xpath->query('.//*[local-name() = \'dest\']', $inf)->item(0);
        $recipientDoc = $this->firstText($xpath, './/*[local-name() = \'CNPJ\']', $dest)
            ?? $this->firstText($xpath, './/*[local-name() = \'CPF\']', $dest);

        $totalNode = $xpath->query('.//*[local-name() = \'ICMSTot\']', $inf)->item(0);
        $vNF = $this->firstText($xpath, './/*[local-name() = \'vNF\']', $totalNode ?? $inf);

        $cfop = null;
        $dets = $xpath->query('.//*[local-name() = \'det\']', $inf);
        if ($dets->length > 0) {
            /** @var \DOMElement $firstDet */
            $firstDet = $dets->item(0);
            $prod = $xpath->query('.//*[local-name() = \'prod\']', $firstDet)->item(0);
            $cfop = $this->firstText($xpath, './/*[local-name() = \'CFOP\']', $prod ?? $firstDet);
        }

        return new NfeParsedData(
            chave: $chave,
            modelo: $mod,
            serie: $serie,
            numero: $numero,
            issuedAt: $issuedAt,
            emitterCnpj: $emitterCnpj,
            recipientDoc: $recipientDoc,
            total: $vNF,
            cfop: $cfop,
        );
    }

    private function firstText(DOMXPath $xpath, string $query, ?\DOMNode $context): ?string
    {
        if ($context === null) {
            return null;
        }
        $n = $xpath->query($query, $context)->item(0);

        return $n?->textContent !== null && $n->textContent !== '' ? trim($n->textContent) : null;
    }

    private function firstInt(DOMXPath $xpath, string $query, ?\DOMNode $context): ?int
    {
        $t = $this->firstText($xpath, $query, $context);
        if ($t === null || ! ctype_digit($t)) {
            return null;
        }

        return (int) $t;
    }
}
