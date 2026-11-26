<?php

namespace App\Services\Documents;

use App\Enums\DocumentOperationType;
use App\Models\FiscalDocument;
use App\Support\Tenant\Cnpj;
use Illuminate\Database\Eloquent\Builder;

/**
 * Role-based "Operação" rules (legacy api.php): classification depends on whether the user's own
 * branches emitted or received the note, not on the CFOP. Filter categories overlap on purpose.
 */
final class DocumentOperationRules
{
    /** Notes excluded from every operation filter (legacy: 5117/6117). */
    private const EXCLUDED_CFOPS = ['5117', '6117'];

    /**
     * @param  list<string>  $branchDigits
     */
    public static function applyFilter(Builder $query, DocumentOperationType $type, array $branchDigits): void
    {
        if ($branchDigits === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q): void {
            $q->whereNull('xml.cfop_nf')->orWhereNotIn('xml.cfop_nf', self::EXCLUDED_CFOPS);
        });

        $sameRoot = 'SUBSTR(xml.cnpj_dest, 1, 8) = SUBSTR(xml.cnpj_emit, 1, 8)';
        $isEntryCfop = "(xml.cfop_nf LIKE '____' AND xml.cfop_nf < '5000')";

        $query->where(function (Builder $q) use ($type, $branchDigits, $sameRoot, $isEntryCfop): void {
            match ($type) {
                DocumentOperationType::Compras => $q->whereIn('xml.cnpj_dest', $branchDigits)
                    ->whereRaw('NOT ('.$sameRoot.')'),
                DocumentOperationType::Saida => $q->whereIn('xml.cnpj_emit', $branchDigits),
                DocumentOperationType::Transferencia => $q->whereIn('xml.cnpj_dest', $branchDigits)
                    ->whereRaw($sameRoot),
                DocumentOperationType::EntradasGerais => $q->whereIn('xml.cnpj_dest', $branchDigits)
                    ->orWhere(function (Builder $e) use ($branchDigits, $isEntryCfop): void {
                        $e->whereIn('xml.cnpj_emit', $branchDigits)->whereRaw($isEntryCfop);
                    }),
                DocumentOperationType::Devolucoes => $q->whereIn('xml.cnpj_emit', $branchDigits)
                    ->whereRaw($isEntryCfop),
            };
        });
    }

    /**
     * Single most-specific label for display.
     *
     * @param  list<string>  $branchDigits
     */
    public static function labelFor(FiscalDocument $doc, array $branchDigits): ?DocumentOperationType
    {
        $emit = Cnpj::digits((string) ($doc->getAttributes()['cnpj_emit'] ?? ''));
        $dest = Cnpj::digits((string) ($doc->getAttributes()['cnpj_dest'] ?? ''));
        $emitMine = $emit !== '' && in_array($emit, $branchDigits, true);
        $destMine = $dest !== '' && in_array($dest, $branchDigits, true);

        if ($destMine && substr($dest, 0, 8) === substr($emit, 0, 8)) {
            return DocumentOperationType::Transferencia;
        }

        if ($emitMine) {
            $cfop = (string) ($doc->getAttributes()['cfop_nf'] ?? '');

            return strlen($cfop) === 4 && $cfop < '5000'
                ? DocumentOperationType::Devolucoes
                : DocumentOperationType::Saida;
        }

        return $destMine ? DocumentOperationType::Compras : null;
    }
}
