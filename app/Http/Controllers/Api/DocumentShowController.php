<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FiscalDocument;
use App\Services\Documents\DocumentListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentShowController extends Controller
{
    public function show(Request $request, FiscalDocument $fiscalDocument): JsonResponse
    {
        $this->authorize('view', $fiscalDocument);

        $query = DocumentListQuery::forUser($request->user())
            ->whereKey($fiscalDocument->id);
        DocumentListQuery::withSpedColumns($query, $request->user());

        /** @var FiscalDocument|null $doc */
        $doc = $query->with('branch')->first();

        if ($doc === null) {
            abort(404);
        }

        return response()->json($this->documentPayload($doc));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(FiscalDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'chave' => $doc->chave,
            'organization_id' => $doc->organization_id,
            'branch_id' => $doc->branch_id,
            'modelo' => $doc->modelo,
            'serie' => $doc->serie,
            'numero' => $doc->numero,
            'issued_at' => $doc->issued_at?->toIso8601String(),
            'emitter_cnpj' => $doc->emitter_cnpj,
            'recipient_doc' => $doc->recipient_doc,
            'total' => $doc->total,
            'cfop' => $doc->cfop,
            'operation_type' => $doc->operation_type?->value,
            'operation_label' => $doc->operation_type?->label(),
            'sped_matched' => (bool) ($doc->sped_matched ?? false),
            'sped_total_nf' => isset($doc->sped_total_nf) ? (string) $doc->sped_total_nf : null,
            'sped_reconcile' => $doc->sped_reconcile,
            'cancelled_at' => $doc->cancelled_at?->toIso8601String(),
            'branch' => $doc->branch ? [
                'id' => $doc->branch->id,
                'name' => $doc->branch->name,
                'cnpj' => $doc->branch->cnpj,
            ] : null,
        ];
    }
}
