<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenant\Cnpj;

/**
 * Resolves the branch ("filial") of a document without per-row queries: the user's branches are
 * loaded once (one query for organizations, one for branches) and indexed by normalized CNPJ in PHP.
 */
final class BranchNameResolver
{
    /**
     * @param  array<string, string>  $namesByCnpj  digits-only CNPJ => branch name
     * @param  array<int, string>  $namesByCode  branch code => branch name
     */
    private function __construct(
        private readonly array $namesByCnpj,
        private readonly array $namesByCode,
        private readonly string $organizationName = '',
    ) {}

    public static function forUser(User $user): self
    {
        $orgs = Organization::query()->forUser($user)->get(['id', 'social', 'fantasia']);
        $orgIds = $orgs->pluck('id');

        if ($orgIds->isEmpty()) {
            return new self([], []);
        }

        $orgName = $orgs->map(fn ($o) => trim((string) $o->social) !== '' ? (string) $o->social : (string) $o->fantasia)
            ->filter()->unique()->implode(', ');

        $byCnpj = [];
        $byCode = [];

        foreach (Branch::query()->whereIn('empresa', $orgIds)->get(['codigo', 'social', 'fantasia', 'cnpj']) as $branch) {
            $name = trim((string) $branch->social) !== '' ? (string) $branch->social : (string) $branch->fantasia;
            $byCode[(int) $branch->codigo] = $name;

            $digits = Cnpj::digits((string) $branch->cnpj);
            if ($digits !== '') {
                $byCnpj[$digits] = $name;
            }
        }

        return new self($byCnpj, $byCode, $orgName);
    }

    /**
     * Branch of the document: the issuer when it is one of the user's branches (saída), otherwise
     * the recipient (entrada).
     */
    public function nameFor(?string $emitterCnpj, ?string $recipientCnpj): ?string
    {
        return $this->namesByCnpj[Cnpj::digits($emitterCnpj)]
            ?? $this->namesByCnpj[Cnpj::digits($recipientCnpj)]
            ?? null;
    }

    public function organizationName(): string
    {
        return $this->organizationName;
    }

    public function nameForCode(int $code): ?string
    {
        return $this->namesByCode[$code] ?? null;
    }
}
