<?php

namespace App\Http\Controllers;

use App\Enums\DocumentOperationType;
use App\Http\Controllers\Concerns\InteractsWithFilteredDocuments;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Services\Tenant\ActiveOrganizationSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentsIndexController extends Controller
{
    use InteractsWithFilteredDocuments;

    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', FiscalDocument::class);

        $perPage = 15;

        $query = $this->documentsQueryFromRequest($request, withSpedColumns: true)
            ->with('branch');

        $filters = $this->documentFilters($request);
        $periodParams = array_filter([
            'issued_from' => $filters->issuedFrom,
            'issued_to' => $filters->issuedTo,
        ], fn ($v) => $v !== null && $v !== '');

        $documents = $query->paginate($perPage)->appends(array_merge($request->query(), $periodParams));

        $orgIds = ActiveOrganizationSession::scopedOrganizationIds($request->user());

        return view('documents.index', [
            'documents' => $documents,
            'filterParams' => $request->query(),
            'issuedFrom' => $filters->issuedFrom,
            'issuedTo' => $filters->issuedTo,
            'periodIsDefault' => ! $request->filled('issued_from') && ! $request->filled('issued_to') && $periodParams !== [],
            'branches' => Branch::query()
                ->whereIn('empresa', $orgIds->all())
                ->orderBy('social')
                ->get(),
            'spedReconcileOptions' => [
                '' => __('documents.filter_sped_all'),
                'none' => __('documents.filter_sped_none'),
                'unknown' => __('documents.filter_sped_unknown'),
                'ok' => __('documents.filter_sped_ok'),
                'diff' => __('documents.filter_sped_diff'),
            ],
            'operationTypes' => DocumentOperationType::cases(),
        ]);
    }
}
