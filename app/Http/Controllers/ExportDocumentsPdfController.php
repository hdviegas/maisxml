<?php

namespace App\Http\Controllers;

use App\Enums\DocumentOperationType;
use App\Http\Controllers\Concerns\InteractsWithFilteredDocuments;
use App\Models\FiscalDocument;
use App\Services\Audit\ActivityLogService;
use App\Services\Documents\BranchNameResolver;
use App\Services\Documents\DocumentListQuery;
use App\Support\Tenant\Cnpj;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ExportDocumentsPdfController extends Controller
{
    use InteractsWithFilteredDocuments;

    /**
     * DomPDF keeps the whole layout in memory (~0.35 MB per 1.000 rows of this table, measured); beyond this
     * the PDF gets slow and may exceed shared-hosting memory limits. The CSV export has no such cap.
     */
    public const MAX_ROWS = 1000;

    private const LIST_COLUMNS = [
        'xml.id', 'xml.chave', 'xml.data_nf', 'xml.modelo_nf', 'xml.numero_nf', 'xml.serie_nf',
        'xml.cnpj_emit', 'xml.cnpj_dest', 'xml.social_emit', 'xml.social_dest', 'xml.total_nf', 'xml.cfop_nf',
    ];

    public function __invoke(Request $request, ActivityLogService $activityLog): Response
    {
        $this->authorize('viewAny', FiscalDocument::class);

        // Raise (never lower) the limits for the heavy DomPDF layout pass.
        if (($bytes = $this->memoryLimitBytes()) !== -1 && $bytes < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        @set_time_limit(120);

        $user = $request->user();
        $filters = $this->auditFilterSnapshot($request);
        // SPED columns (and their two correlated subqueries per row) only matter when the user filtered by them.
        $withSped = isset($filters['sped_reconcile']);

        $base = $this->documentsQueryFromRequest($request);

        // One aggregate query for the whole filtered set (count + sum), independent of the row limit.
        $aggregate = (clone $base)->reorder()->toBase()
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(xml.total_nf), 0) as aggregate_total')
            ->first();
        $matchedCount = (int) ($aggregate->aggregate_count ?? 0);
        $matchedTotal = (float) ($aggregate->aggregate_total ?? 0);

        $listQuery = $base->limit(self::MAX_ROWS);
        $withSped
            ? DocumentListQuery::withSpedColumns($listQuery, $user)
            : $listQuery->select(self::LIST_COLUMNS);
        $documents = $listQuery->get();

        $resolver = BranchNameResolver::forUser($user);
        $rows = $documents->map(fn (FiscalDocument $doc): array => [
            'chave' => (string) $doc->chave,
            'issued_at' => $doc->issued_at?->timezone(config('app.timezone')),
            'modelo' => $doc->modelo,
            'numero' => $doc->numero,
            'serie' => $doc->serie,
            'branch' => $resolver->nameFor($doc->emitter_cnpj, $doc->recipient_doc),
            'operation' => $doc->operation_type?->label(),
            'emitter' => $this->party($doc->social_emit, $doc->emitter_cnpj),
            'recipient' => $this->party($doc->social_dest, $doc->recipient_doc),
            'total' => $doc->total !== null ? (float) $doc->total : null,
            'sped' => $withSped ? $doc->sped_reconcile : null,
        ])->all();

        $activityLog->record(
            $user,
            'documents.export_pdf',
            null,
            array_merge($filters, [
                'row_count' => count($rows),
                'matched_count' => $matchedCount,
                'format' => 'pdf',
                'max_rows' => self::MAX_ROWS,
            ]),
        );

        $pdf = Pdf::loadView('pdf.documents-list', [
            'title' => __('documents.pdf_title'),
            'rows' => $rows,
            'with_sped' => $withSped,
            'shown_total' => array_sum(array_map(fn (array $r): float => (float) $r['total'], $rows)),
            'matched_count' => $matchedCount,
            'matched_total' => $matchedTotal,
            'truncated' => $matchedCount > count($rows),
            'organization' => $resolver->organizationName(),
            'user_name' => (string) ($user->name ?? $user->email ?? ''),
            'filter_lines' => $this->filterLines($filters, $resolver),
            'generated_at' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption(['enable_font_subsetting' => true, 'enable_remote' => false, 'enable_php' => false]);

        // Render now so the "Página X de Y" footer can be drawn on the final canvas.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $dompdf->getCanvas()->page_text(
            730, 566, __('documents.pdf_page').' {PAGE_NUM} '.__('documents.pdf_of').' {PAGE_COUNT}', $font, 7, [0.35, 0.35, 0.35],
        );

        return $pdf->download('relatorio-documentos-'.now()->format('Y-m-d').'.pdf');
    }

    private function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }

        return (int) $raw * match (strtolower(substr($raw, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
    }

    private function party(?string $name, ?string $cnpj): string
    {
        $name = trim((string) $name);
        $digits = Cnpj::digits($cnpj);

        return Str::limit($name !== '' ? $name : $digits, 30, '…');
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<string>
     */
    private function filterLines(array $f, BranchNameResolver $resolver): array
    {
        $lines = [];

        $from = $f['issued_from'] ?? null;
        $to = $f['issued_to'] ?? null;
        $fmt = fn (?string $d): string => $d !== null && strtotime($d) !== false ? date('d/m/Y', strtotime($d)) : '…';
        if ($from !== null || $to !== null) {
            $lines[] = __('documents.pdf_filter_period').': '.$fmt($from).' – '.$fmt($to);
        }

        if (! empty($f['branch_id'])) {
            $names = collect((array) $f['branch_id'])
                ->map(fn ($code) => $resolver->nameForCode((int) $code) ?? '#'.$code)->implode(', ');
            $lines[] = __('documents.filter_branch').': '.$names;
        }

        if (! empty($f['operation_type'])) {
            $op = DocumentOperationType::tryFrom((int) $f['operation_type']);
            if ($op !== null) {
                $lines[] = __('documents.col_operation').': '.$op->label();
            }
        }

        $simple = [
            'q' => 'documents.search_chave',
            'modelo' => 'documents.filter_modelo',
            'numero' => 'documents.filter_numero',
            'serie' => 'documents.filter_serie',
            'emitter_digits' => 'documents.filter_emitter',
            'recipient_digits' => 'documents.filter_recipient',
        ];
        foreach ($simple as $key => $label) {
            if (isset($f[$key]) && $f[$key] !== '') {
                $lines[] = __($label).': '.$f[$key];
            }
        }

        if (! empty($f['sped_reconcile'])) {
            $lines[] = __('documents.filter_sped_reconcile').': '.__('documents.reconcile_'.$f['sped_reconcile']);
        }

        return $lines;
    }
}
