<?php

namespace App\Services\Sieg;

final class SiegDownloadResult
{
    /**
     * @param  list<SiegDocument>  $documents
     */
    public function __construct(
        public readonly array $documents = [],
        public readonly bool $notFound = false,
    ) {}
}
