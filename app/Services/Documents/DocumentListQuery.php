<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenant\Cnpj;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class DocumentListQuery
{
    public static function forUser(User $user): Builder
    {
        $orgIds = Organization::query()->forUser($user)->pluck('id');

        if ($orgIds->isEmpty()) {
            return FiscalDocument::query()->whereRaw('1 = 0');
        }

        $digits = Branch::query()
            ->whereIn('empresa', $orgIds)
            ->pluck('cnpj')
            ->map(fn ($c) => Cnpj::digits((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($digits === []) {
            return FiscalDocument::query()->whereRaw('1 = 0');
        }

        $emit = Cnpj::sqlNormalizeColumn('xml.cnpj_emit');
        $dest = Cnpj::sqlNormalizeColumn('xml.cnpj_dest');

        $place = implode(',', array_fill(0, count($digits), '?'));

        return FiscalDocument::query()->whereRaw(
            "({$emit} IN ({$place}) OR {$dest} IN ({$place}))",
            [...$digits, ...$digits]
        );
    }

    public static function applyFilters(Builder $query, DocumentListFilters $filters, User $user): Builder
    {
        $term = trim((string) $filters->q);
        if ($term !== '') {
            $safe = preg_replace('/[^\d]/', '', $term);
            if ($safe !== '') {
                $query->where('xml.chave', 'like', '%'.$safe.'%');
            }
        }

        if ($filters->branchId) {
            $branch = Branch::query()->find($filters->branchId);
            if ($branch !== null) {
                $c = Cnpj::digits((string) $branch->cnpj);
                if ($c !== '') {
                    $emit = Cnpj::sqlNormalizeColumn('xml.cnpj_emit');
                    $dest = Cnpj::sqlNormalizeColumn('xml.cnpj_dest');
                    $query->whereRaw("({$emit} = ? OR {$dest} = ?)", [$c, $c]);
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

        if ($filters->issuedFrom !== null && $filters->issuedFrom !== '') {
            $query->whereDate('xml.data_nf', '>=', $filters->issuedFrom);
        }

        if ($filters->issuedTo !== null && $filters->issuedTo !== '') {
            $query->whereDate('xml.data_nf', '<=', $filters->issuedTo);
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

        $spedCnpj = Cnpj::sqlNormalizeColumn('s.cnpj');
        $exists = 'EXISTS (SELECT 1 FROM sped s WHERE s.chave_nf = xml.chave AND '.$spedCnpj.' = ?)';
        $spedTotal = '(SELECT s.total_nf FROM sped s WHERE s.chave_nf = xml.chave AND '.$spedCnpj.' = ? ORDER BY s.data_nf DESC LIMIT 1)';

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

        $spedCnpj = Cnpj::sqlNormalizeColumn('s.cnpj');
        $exists = 'EXISTS (SELECT 1 FROM sped s WHERE s.chave_nf = xml.chave AND '.$spedCnpj.' = ?)';
        $total = '(SELECT s.total_nf FROM sped s WHERE s.chave_nf = xml.chave AND '.$spedCnpj.' = ? ORDER BY s.data_nf DESC LIMIT 1)';

        return $query->select('xml.*')
            ->selectRaw('('.$exists.') as sped_matched', [$cnpj])
            ->selectRaw($total.' as sped_total_nf', [$cnpj]);
    }
}
