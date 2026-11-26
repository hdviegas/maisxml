<?php

namespace App\Models;

use App\Enums\DocumentOperationType;
use App\Services\Cfop\OperationClassifier;
use App\Support\Tenant\Cnpj;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy NF-e row stored in table `xml` (see docs/MEMORY_LEGACY_MAISXML.md).
 *
 * Domain property names (organization_id, storage_path, issued_at, …) are provided via
 * accessors/mutators where the physical column names differ (`url`, `data_nf`, …).
 */
class FiscalDocument extends Model
{
    protected $table = 'xml';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'url',
        'chave',
        'empresa',
        'social',
        'dest',
        'tipo',
        'data_nf',
        'ip',
        'cnpj_emit',
        'cnpj_dest',
        'social_emit',
        'social_dest',
        'numero_nf',
        'modelo_nf',
        'serie_nf',
        'total_nf',
        'total_icms',
        'total_icms_st',
        'total_frete',
        'cfop_nf',
        'cancelada',
        'sped',
    ];

    protected $appends = [];

    protected function casts(): array
    {
        return [
            'data_nf' => 'datetime',
            'cancelada' => 'boolean',
            'total_nf' => 'decimal:2',
        ];
    }

    public function getStoragePathAttribute(): string
    {
        return (string) ($this->attributes['url'] ?? '');
    }

    public function setStoragePathAttribute(?string $value): void
    {
        $this->attributes['url'] = $value ?? '';
    }

    public function getIssuedAtAttribute(): ?Carbon
    {
        return $this->data_nf;
    }

    public function setIssuedAtAttribute(mixed $value): void
    {
        $this->attributes['data_nf'] = $value;
    }

    public function getModeloAttribute(): ?string
    {
        $v = $this->attributes['modelo_nf'] ?? null;

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    public function setModeloAttribute(?string $value): void
    {
        $this->attributes['modelo_nf'] = $value;
    }

    public function getSerieAttribute(): ?int
    {
        $v = $this->attributes['serie_nf'] ?? null;

        return $v !== null && $v !== '' ? (int) $v : null;
    }

    public function setSerieAttribute(mixed $value): void
    {
        $this->attributes['serie_nf'] = $value;
    }

    public function getNumeroAttribute(): ?int
    {
        $v = $this->attributes['numero_nf'] ?? null;

        return $v !== null && $v !== '' ? (int) $v : null;
    }

    public function setNumeroAttribute(mixed $value): void
    {
        $this->attributes['numero_nf'] = $value;
    }

    public function getEmitterCnpjAttribute(): ?string
    {
        $v = $this->attributes['cnpj_emit'] ?? null;

        return $v !== null && (string) $v !== '' ? (string) $v : null;
    }

    public function setEmitterCnpjAttribute(?string $value): void
    {
        $this->attributes['cnpj_emit'] = $value;
    }

    public function getRecipientDocAttribute(): ?string
    {
        $v = $this->attributes['cnpj_dest'] ?? null;

        return $v !== null && (string) $v !== '' ? (string) $v : null;
    }

    public function setRecipientDocAttribute(?string $value): void
    {
        $this->attributes['cnpj_dest'] = $value;
    }

    public function getTotalAttribute(): ?string
    {
        $v = $this->attributes['total_nf'] ?? null;

        return $v !== null ? (string) $v : null;
    }

    public function setTotalAttribute(mixed $value): void
    {
        $this->attributes['total_nf'] = $value;
    }

    public function getCfopAttribute(): ?string
    {
        $v = $this->attributes['cfop_nf'] ?? null;

        return $v !== null && (string) $v !== '' ? (string) $v : null;
    }

    public function setCfopAttribute(?string $value): void
    {
        $this->attributes['cfop_nf'] = $value;
    }

    /**
     * Legacy `cancelada` is boolean only (no cancellation timestamp stored).
     */
    public function getCancelledAtAttribute(): ?Carbon
    {
        return null;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'cnpj_emit', 'cnpj');
    }

    public function getOrganizationIdAttribute(): ?int
    {
        $emit = Cnpj::digits((string) ($this->attributes['cnpj_emit'] ?? ''));
        if ($emit === '') {
            return null;
        }

        return Organization::idsMatchingCnpj($emit)[0] ?? null;
    }

    public function getBranchIdAttribute(): ?int
    {
        $emit = Cnpj::digits((string) ($this->attributes['cnpj_emit'] ?? ''));

        if ($emit === '') {
            return null;
        }

        return Branch::codesMatchingCnpj($emit)[0] ?? null;
    }

    /**
     * If `operation_type` exists on the row (optional column), use it; otherwise derive from CFOP.
     */
    public function getOperationTypeAttribute(): ?DocumentOperationType
    {
        if (isset($this->attributes['operation_type'])) {
            $enum = DocumentOperationType::tryFrom((int) $this->attributes['operation_type']);
            if ($enum !== null) {
                return $enum;
            }
        }

        return app(OperationClassifier::class)->classify($this->cfop);
    }

    /**
     * Reflects reconciliation between NF-e totals and SPED when `DocumentListQuery::withSpedColumns` populated the aliases.
     */
    public function getSpedReconcileAttribute(): string
    {
        if (! array_key_exists('sped_matched', $this->attributes)) {
            return 'none';
        }

        $matchedRaw = $this->attributes['sped_matched'] ?? false;
        $matched = filter_var($matchedRaw, FILTER_VALIDATE_BOOLEAN) || ($matchedRaw === 1 || $matchedRaw === '1');

        if (! $matched) {
            return 'none';
        }

        $xmlTotal = $this->attributes['total_nf'] ?? null;
        $spedTotal = $this->attributes['sped_total_nf'] ?? null;

        if ($xmlTotal === null || $spedTotal === null || $spedTotal === '') {
            return 'unknown';
        }

        if (abs((float) $xmlTotal - (float) $spedTotal) <= 0.02) {
            return 'ok';
        }

        return 'diff';
    }
}
