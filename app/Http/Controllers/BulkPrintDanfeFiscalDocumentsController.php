<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use App\Services\Audit\ActivityLogService;
use App\Services\Nfe\NfeDanfeBatchGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BulkPrintDanfeFiscalDocumentsController extends Controller
{
    public function __invoke(
        Request $request,
        NfeDanfeBatchGenerator $batch,
        ActivityLogService $activityLog,
    ): Response|RedirectResponse {
        $this->authorize('viewAny', FiscalDocument::class);

        $max = (int) config('documents.bulk_danfe_max', 100);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.$max],
            'ids.*' => ['integer', 'exists:xml,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $byId = FiscalDocument::query()->whereIn('id', $ids)->get()->keyBy('id');

        // Mesma regra do ZIP: qualquer documento fora do tenant do usuario aborta com 403.
        foreach ($byId as $doc) {
            $this->authorize('view', $doc);
        }

        // Preserva a ordem da selecao (= ordem da listagem).
        $ordered = collect($ids)->map(fn (int $id) => $byId->get($id))->filter()->values();

        @set_time_limit(300);

        $result = $batch->merge($ordered, Storage::disk('local'));

        $activityLog->record(
            $request->user(),
            'documents.bulk_danfe',
            null,
            [
                'ids' => $ids,
                'count' => count($result['included']),
                'skipped_chaves' => $result['skipped'],
            ],
            $ordered->first()?->organization_id,
        );

        if ($result['pdf'] === null) {
            return back()->withErrors(['ids' => __('documents.bulk_danfe_none')]);
        }

        $filename = 'DANFEs-'.now()->format('Y-m-d-Hi').'.pdf';

        return response($result['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
