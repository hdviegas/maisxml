<?php

namespace App\Services\Sped;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Sped;
use App\Models\User;
use App\Support\Tenant\Cnpj;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SpedC100ImportService
{
    public function __construct(
        protected SpedC100Parser $parser,
    ) {}

    public function importFromText(User $user, Branch $branch, string $text): int
    {
        $branchOrgId = (int) $branch->empresa;
        if (! Organization::query()->forUser($user)->whereKey($branchOrgId)->exists()) {
            throw new InvalidArgumentException('branch_forbidden');
        }

        if (trim($text) === '') {
            throw new InvalidArgumentException('empty_sped');
        }

        $cnpj = Cnpj::digits((string) $user->cnpj);
        if ($cnpj === '') {
            throw new InvalidArgumentException('user_cnpj_required');
        }

        $count = 0;

        DB::transaction(function () use ($text, $cnpj, &$count): void {
            foreach ($this->parser->lines($text) as $row) {
                if (empty($row['chave_nf'])) {
                    continue;
                }

                Sped::query()->create([
                    'cnpj' => $cnpj,
                    'data_nf' => $row['data_nf'],
                    'chave_nf' => $row['chave_nf'],
                    'total_nf' => $row['total_nf'],
                    'total_icms' => $row['total_icms'],
                ]);
                $count++;
            }
        });

        return $count;
    }
}
