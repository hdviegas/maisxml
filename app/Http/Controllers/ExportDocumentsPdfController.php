<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithFilteredDocuments;
use App\Models\FiscalDocument;
use App\Services\Audit\ActivityLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportDocumentsPdfController extends Controller
{
    use InteractsWithFilteredDocuments;

    public function __invoke(Request $request, ActivityLogService $activityLog): Response
    {
        $this->authorize('viewAny', FiscalDocument::class);

        $query = $this->documentsQueryFromRequest($request, withSpedColumns: true);
        $documents = $query->limit(2000)->get();

        $activityLog->record(
            $request->user(),
            'documents.export_pdf',
            null,
            array_merge($this->auditFilterSnapshot($request), [
                'row_count' => $documents->count(),
                'format' => 'pdf',
                'max_rows' => 2000,
            ]),
        );

        $pdf = Pdf::loadView('pdf.documents-list', [
            'documents' => $documents,
            'title' => __('documents.title_index'),
            'generated_at' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('documentos_fiscais_'.now()->format('Y-m-d').'.pdf');
    }
}
