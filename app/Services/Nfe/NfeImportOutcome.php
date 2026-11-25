<?php

namespace App\Services\Nfe;

use App\Models\FiscalDocument;

final class NfeImportOutcome
{
    public function __construct(
        public FiscalDocument $document,
        public string $result,
    ) {}

    public function wasCreated(): bool
    {
        return $this->result === 'created';
    }

    public function wasSkipped(): bool
    {
        return $this->result === 'skipped';
    }

    public function wasReplaced(): bool
    {
        return $this->result === 'replaced';
    }
}
