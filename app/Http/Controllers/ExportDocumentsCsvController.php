<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithFilteredDocuments;
use App\Models\FiscalDocument;
use App\Services\Audit\ActivityLogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDocumentsCsvController extends Controller
{
    use InteractsWithFilteredDocuments;

    public function __invoke(Request $request, ActivityLogService $activityLog): StreamedResponse
    {
        $this->authorize('viewAny', FiscalDocument::class);

        $query = $this->documentsQueryFromRequest($request, withSpedColumns: true);
        $rowCount = (clone $query)->count();

        $activityLog->record(
            $request->user(),
            'documents.export_csv',
            null,
            array_merge($this->auditFilterSnapshot($request), [
                'row_count' => $rowCount,
                'format' => 'csv',
            ]),
        );

        $filename = 'documentos_fiscais_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                __('documents.col_chave'),
                __('documents.col_issue'),
                __('documents.col_model'),
                __('documents.col_branch'),
                __('documents.col_total'),
                __('documents.col_cfop'),
                __('documents.col_operation'),
                __('documents.col_sped'),
                __('documents.col_sped_total'),
                __('documents.col_sped_reconcile'),
            ], ';');

            foreach ($query->cursor() as $doc) {
                fputcsv($out, [
                    $doc->chave,
                    $doc->issued_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    $doc->modelo,
                    $doc->branch?->name,
                    $doc->total !== null ? number_format((float) $doc->total, 2, ',', '.') : '',
                    $doc->cfop,
                    $doc->operation_type?->label(),
                    ! empty($doc->sped_matched) ? __('documents.sped_yes') : __('documents.sped_no'),
                    isset($doc->sped_total_nf) && $doc->sped_total_nf !== null
                        ? number_format((float) $doc->sped_total_nf, 2, ',', '.') : '',
                    match ($doc->sped_reconcile ?? 'none') {
                        'ok' => __('documents.reconcile_ok'),
                        'diff' => __('documents.reconcile_diff'),
                        'unknown' => __('documents.reconcile_unknown'),
                        default => __('documents.reconcile_none'),
                    },
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
