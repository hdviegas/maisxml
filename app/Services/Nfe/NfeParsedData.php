<?php

namespace App\Services\Nfe;

use Carbon\CarbonInterface;

readonly class NfeParsedData
{
    public function __construct(
        public string $chave,
        public ?string $modelo,
        public ?int $serie,
        public ?int $numero,
        public ?CarbonInterface $issuedAt,
        public ?string $emitterCnpj,
        public ?string $recipientDoc,
        public ?string $total,
        public ?string $cfop,
        public ?string $cStat = null,
    ) {}
}
