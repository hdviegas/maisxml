<?php

namespace App\Models;

use App\Support\Tenant\Cnpj;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $table = 'empresa';

    public $timestamps = false;

    protected $fillable = [
        'social',
        'fantasia',
        'cnpj',
    ];

    /**
     * Companies the given legacy user can access (matches legacy login join on CNPJ).
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        $digits = Cnpj::digits((string) $user->cnpj);
        if ($digits === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey(self::idsMatchingCnpj($digits));
    }

    /**
     * IDs of organizations whose (possibly dirty-formatted) CNPJ equals $digits.
     *
     * Normalization happens in PHP: `empresa` is tiny and may hold formatting garbage,
     * whereas wrapping the column in SQL functions would defeat indexes.
     *
     * @return list<int>
     */
    public static function idsMatchingCnpj(string $digits): array
    {
        if ($digits === '') {
            return [];
        }

        return static::query()
            ->get(['id', 'cnpj'])
            ->filter(fn ($o) => Cnpj::digits((string) $o->cnpj) === $digits)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Normalized CNPJ digits of an organization's branches (`empresa_filial.cnpj`).
     *
     * Must mirror DocumentListQuery::forUser, so that "can import/replace" == "can see".
     * The matriz CNPJ (`empresa.cnpj`) counts only if it is also registered as a branch.
     *
     * @return list<string>
     */
    public static function cnpjDigitsFor(int $organizationId): array
    {
        return Branch::query()
            ->where('empresa', $organizationId)
            ->pluck('cnpj')
            ->map(fn ($c) => Cnpj::digits((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'empresa', 'id');
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->social ?? '');
    }

    public function getTradeNameAttribute(): ?string
    {
        $f = $this->fantasia ?? null;

        return $f !== null && $f !== '' ? (string) $f : null;
    }
}
