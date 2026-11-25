<?php

namespace App\Services\Nfe;

use DOMDocument;
use NFePHP\DA\NFe\Danfce;
use NFePHP\DA\NFe\Danfe;
use Throwable;

class NfeDanfeGenerator
{
    public function detectIdeModFromXml(string $xml): ?string
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML(trim($xml))) {
            return null;
        }

        $ide = $dom->getElementsByTagName('ide')->item(0);
        if (! $ide) {
            return null;
        }

        $mod = $ide->getElementsByTagName('mod')->item(0);

        return $mod ? trim($mod->nodeValue) : null;
    }

    /**
     * @throws Throwable
     */
    public function pdfBinary(string $xml, ?string $modeloHint = null): string
    {
        $mod = $modeloHint ?? $this->detectIdeModFromXml($xml) ?? '55';

        $level = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        try {
            if ($mod === '65') {
                $da = new Danfce($xml);
            } else {
                $da = new Danfe($xml);
            }

            return $da->render();
        } finally {
            error_reporting($level);
        }
    }
}
