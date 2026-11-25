<?php

namespace App\Services\Nfe;

use Carbon\CarbonInterface;

readonly class NfeEventData
{
    public function __construct(
        public string $chave,
        public int $tpEvento,
        public int $nSeqEvento,
        public ?CarbonInterface $dhEvento,
        public ?string $authorDoc,
        public ?string $descricao,
        public ?string $xCorrecao,
        public ?string $cStat,
        public ?string $protocolo,
    ) {}

    public function isCancellation(): bool
    {
        return in_array($this->tpEvento, [110111, 110112], true)
            && in_array($this->cStat, ['135', '136', '155'], true);
    }
}
