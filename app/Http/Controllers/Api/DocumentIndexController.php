<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithFilteredDocuments;
use App\Http\Controllers\Controller;
use App\Models\FiscalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentIndexController extends Controller
{
    use InteractsWithFilteredDocuments;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FiscalDocument::class);

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $query = $this->documentsQueryFromRequest($request, withSpedColumns: true);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(function (FiscalDocument $doc) {
                return [
                    'id' => $doc->id,
                    'chave' => $doc->chave,
                    'organization_id' => $doc->organization_id,
                    'branch_id' => $doc->branch_id,
                    'modelo' => $doc->modelo,
                    'serie' => $doc->serie,
                    'numero' => $doc->numero,
                    'issued_at' => $doc->issued_at?->toIso8601String(),
                    'total' => $doc->total,
                    'cfop' => $doc->cfop,
                    'operation_type' => $doc->operation_type?->value,
                    'operation_label' => $doc->operation_type?->label(),
                    'sped_matched' => (bool) ($doc->sped_matched ?? false),
                    'sped_total_nf' => isset($doc->sped_total_nf) ? (string) $doc->sped_total_nf : null,
                    'sped_reconcile' => $doc->sped_reconcile,
                    'cancelled_at' => $doc->cancelled_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
