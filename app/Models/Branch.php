<?php

namespace App\Models;

use App\Support\Tenant\Cnpj;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $table = 'empresa_filial';

    protected $primaryKey = 'codigo';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'empresa',
        'social',
        'fantasia',
        'cnpj',
        'entrada',
    ];

    protected function casts(): array
    {
        return [
            'entrada' => 'boolean',
        ];
    }

    /**
     * Branch codes whose (possibly dirty-formatted) CNPJ equals $digits; normalized in PHP, not SQL.
     *
     * @return list<int>
     */
    public static function codesMatchingCnpj(string $digits): array
    {
        if ($digits === '') {
            return [];
        }

        return static::query()
            ->get(['codigo', 'cnpj'])
            ->filter(fn ($b) => Cnpj::digits((string) $b->cnpj) === $digits)
            ->pluck('codigo')
            ->map(fn ($c) => (int) $c)
            ->values()
            ->all();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'empresa', 'id');
    }

    public function getOrganizationIdAttribute(): int
    {
        return (int) $this->empresa;
    }

    public function getIdAttribute(): int
    {
        return (int) ($this->attributes['codigo'] ?? 0);
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->social ?? '');
    }

    public function getEntradaEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['entrada'] ?? false);
    }
}
