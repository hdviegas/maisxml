<?php

namespace App\Services\Sieg;

/** Um XML extraído da resposta da SIEG. */
final class SiegDocument
{
    public function __construct(
        public readonly string $xml,
        public readonly ?string $filename = null,
    ) {}
}
