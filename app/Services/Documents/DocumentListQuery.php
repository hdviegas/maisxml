<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Support\IssuedPeriod;
use App\Support\Tenant\Cnpj;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class DocumentListQuery
{
    /**
     * Normalized CNPJ digits of every branch of the user's organizations.
     *
     * @return list<string>
     */
    public static function cnpjDigitsForUser(User $user): array
    {
        // Memoize only inside a routed HTTP request (policy checks run once per row); console/jobs/tests
        // must always see fresh data.
        $request = request();
        $key = null;
        if ($request->route() !== null) {
            $key = 'tenant_cnpj_digits.'.$user->getKey().'.'.Cnpj::digits((string) $user->cnpj);
            if ($request->attributes->has($key)) {
                return $request->attributes->get($key);
            }
        }

        $orgIds = Organization::query()->forUser($user)->pluck('id');

        $digits = $orgIds->isEmpty() ? [] : Branch::query()
            ->whereIn('empresa', $orgIds)
            ->pluck('cnpj')
            ->map(fn ($c) => Cnpj::digits((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($key !== null) {
            $request->attributes->set($key, $digits);
        }

        return $digits;
    }

    public static function forUser(User $user): Builder
    {
        $digits = self::cnpjDigitsForUser($user);

        if ($digits === []) {
            return FiscalDocument::query()->whereRaw('1 = 0');
        }

        // xml.cnpj_* hold digits only; compare the raw columns so the indexes stay usable.
        return FiscalDocument::query()->where(function (Builder $q) use ($digits): void {
            $q->whereIn('xml.cnpj_emit', $digits)->orWhereIn('xml.cnpj_dest', $digits);
        });
    }

    public static function applyFilters(Builder $query, DocumentListFilters $filters, User $user): Builder
    {
        $safe = $filters->qDigits();
        if ($safe !== '') {
            if (strlen($safe) === 44) {
                $query->where('xml.chave', $safe);
            } else {
                $query->where('xml.chave', 'like', '%'.$safe.'%');
            }
        }

        if ($filters->branchId) {
            $branch = Branch::query()->find($filters->branchId);
            if ($branch !== null) {
                $c = Cnpj::digits((string) $branch->cnpj);
                if ($c !== '') {
                    $query->where(function (Builder $q) use ($c): void {
                        $q->where('xml.cnpj_emit', $c)->orWhere('xml.cnpj_dest', $c);
                    });
                }
            }
        }

        if (
            $filters->operationType !== null
            && $filters->operationType > 0
            && Schema::hasColumn('xml', 'operation_type')
        ) {
            $query->where('xml.operation_type', $filters->operationType);
        }

        // Sargable range on data_nf (DATE): no DATE() wrapper; end is exclusive (day after).
        $from = IssuedPeriod::parseDate($filters->issuedFrom);
        if ($from !== null) {
            $query->where('xml.data_nf', '>=', $from->toDateString());
        }

        $to = IssuedPeriod::parseDate($filters->issuedTo);
        if ($to !== null) {
            $query->where('xml.data_nf', '<', $to->addDay()->toDateString());
        }

        if ($filters->numero !== null) {
            $query->where('xml.numero_nf', $filters->numero);
        }

        if ($filters->serie !== null) {
            $query->where('xml.serie_nf', $filters->serie);
        }

        if ($filters->modelo !== null) {
            $query->where('xml.modelo_nf', $filters->modelo);
        }

        if ($filters->emitterDigits !== null && $filters->emitterDigits !== '') {
            $query->where('xml.cnpj_emit', 'like', '%'.$filters->emitterDigits.'%');
        }

        if ($filters->recipientDigits !== null && $filters->recipientDigits !== '') {
            $query->where('xml.cnpj_dest', 'like', '%'.$filters->recipientDigits.'%');
        }

        self::applySpedReconcileFilter($query, $filters->spedReconcile, $user);

        return $query;
    }

    private static function applySpedReconcileFilter(Builder $query, ?string $mode, User $user): void
    {
        if ($mode === null) {
            return;
        }

        $cnpj = Cnpj::digits((string) $user->cnpj);
        if ($cnpj === '') {
            return;
        }

        $exists = 'EXISTS (SELECT 1 FROM sped s WHERE s.chave_nf = xml.chave AND s.cnpj = ?)';
        $spedTotal = '(SELECT s.total_nf FROM sped s WHERE s.chave_nf = xml.chave AND s.cnpj = ? ORDER BY s.data_nf DESC LIMIT 1)';

        match ($mode) {
            'none' => $query->whereRaw('NOT '.$exists, [$cnpj]),
            'unknown' => $query->whereRaw(
                $exists.' AND (xml.total_nf IS NULL OR '.$spedTotal.' IS NULL)',
                [$cnpj, $cnpj]
            ),
            'ok' => $query->whereRaw(
                $exists.' AND xml.total_nf IS NOT NULL AND '.$spedTotal.' IS NOT NULL AND ABS(xml.total_nf - '.$spedTotal.') <= 0.02',
                [$cnpj, $cnpj, $cnpj]
            ),
            'diff' => $query->whereRaw(
                $exists.' AND xml.total_nf IS NOT NULL AND '.$spedTotal.' IS NOT NULL AND ABS(xml.total_nf - '.$spedTotal.') > 0.02',
                [$cnpj, $cnpj, $cnpj]
            ),
            default => null,
        };
    }

    public static function withSpedColumns(Builder $query, User $user): Builder
    {
        $cnpj = Cnpj::digits((string) $user->cnpj);

        if ($cnpj === '') {
            return $query->select('xml.*')
                ->selectRaw('0 as sped_matched')
                ->selectRaw('NULL as sped_total_nf');
        }

        $exists = 'EXISTS (SELECT 1 FROM sped s WHERE s.chave_nf = xml.chave AND s.cnpj = ?)';
        $total = '(SELECT s.total_nf FROM sped s WHERE s.chave_nf = xml.chave AND s.cnpj = ? ORDER BY s.data_nf DESC LIMIT 1)';

        return $query->select('xml.*')
            ->selectRaw('('.$exists.') as sped_matched', [$cnpj])
            ->selectRaw($total.' as sped_total_nf', [$cnpj]);
    }
}
