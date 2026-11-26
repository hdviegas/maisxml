<?php

namespace App\Services\Documents;

final class DocumentListFilters
{
    public function __construct(
        public ?string $q = null,
        /** @var list<int> */
        public array $branchIds = [],
        public ?int $operationType = null,
        public ?string $issuedFrom = null,
        public ?string $issuedTo = null,
        public ?int $numero = null,
        public ?int $serie = null,
        public ?string $modelo = null,
        public ?string $emitterDigits = null,
        public ?string $recipientDigits = null,
        public ?string $spedReconcile = null,
    ) {}

    /**
     * Accepts a scalar id, a list of ids, or garbage; returns unique positive ints.
     *
     * @return list<int>
     */
    public static function parseBranchIds(mixed $raw): array
    {
        $ids = [];
        foreach (is_array($raw) ? $raw : [$raw] as $v) {
            if ((is_string($v) || is_int($v)) && ctype_digit((string) $v) && (int) $v > 0 && strlen((string) $v) <= 18) {
                $ids[] = (int) $v;
            }
        }

        return array_values(array_unique($ids));
    }

    /** True when the branch filter was sent (even if every id was invalid/foreign). */
    public function hasBranchFilter(): bool
    {
        return $this->branchIds !== [];
    }

    /** Digits-only form of the free-text search term. */
    public function qDigits(): string
    {
        return preg_replace('/\D/', '', trim((string) $this->q)) ?? '';
    }

    /** True when the search term is a complete 44-digit access key. */
    public function isFullKeySearch(): bool
    {
        return strlen($this->qDigits()) === 44;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromQueryArray(array $query): self
    {
        $op = $query['operation_type'] ?? null;
        $opInt = ($op !== null && $op !== '' && ctype_digit((string) $op)) ? (int) $op : null;
        if ($opInt !== null && ($opInt < 1 || $opInt > 5)) {
            $opInt = null;
        }

        $numero = $query['numero'] ?? null;
        $serie = $query['serie'] ?? null;
        $branchIds = self::parseBranchIds($query['branch_id'] ?? null);

        $rec = $query['sped_reconcile'] ?? null;
        $rec = is_string($rec) && in_array($rec, ['diff', 'ok', 'none', 'unknown'], true) ? $rec : null;

        $modelo = $query['modelo'] ?? null;
        $modelo = is_string($modelo) && in_array($modelo, ['55', '65', '66'], true) ? $modelo : null;

        $emitter = isset($query['emitter']) ? preg_replace('/\D/', '', (string) $query['emitter']) : '';
        $recipient = isset($query['recipient']) ? preg_replace('/\D/', '', (string) $query['recipient']) : '';

        return new self(
            q: isset($query['q']) ? (string) $query['q'] : null,
            branchIds: $branchIds,
            operationType: $opInt,
            issuedFrom: ! empty($query['issued_from']) ? (string) $query['issued_from'] : null,
            issuedTo: ! empty($query['issued_to']) ? (string) $query['issued_to'] : null,
            numero: ($numero !== null && $numero !== '' && ctype_digit((string) $numero)) ? (int) $numero : null,
            serie: ($serie !== null && $serie !== '' && ctype_digit((string) $serie)) ? (int) $serie : null,
            modelo: $modelo,
            emitterDigits: $emitter !== '' ? $emitter : null,
            recipientDigits: $recipient !== '' ? $recipient : null,
            spedReconcile: $rec,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAuditArray(): array
    {
        return array_filter([
            'q' => $this->q,
            'branch_id' => $this->branchIds,
            'operation_type' => $this->operationType,
            'issued_from' => $this->issuedFrom,
            'issued_to' => $this->issuedTo,
            'numero' => $this->numero,
            'serie' => $this->serie,
            'modelo' => $this->modelo,
            'emitter_digits' => $this->emitterDigits,
            'recipient_digits' => $this->recipientDigits,
            'sped_reconcile' => $this->spedReconcile,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
