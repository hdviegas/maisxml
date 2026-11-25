<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use App\Services\Audit\ActivityLogService;
use App\Services\Dashboard\DashboardStatsCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class DestroyFiscalDocumentController extends Controller
{
    public function __invoke(FiscalDocument $fiscalDocument, ActivityLogService $activityLog): RedirectResponse
    {
        $this->authorize('delete', $fiscalDocument);

        $disk = Storage::disk('local');
        $path = $fiscalDocument->storage_path;
        $chave = $fiscalDocument->chave;

        $activityLog->record(
            request()->user(),
            'fiscal_document.deleted',
            $fiscalDocument,
            ['chave' => $chave],
        );

        $fiscalDocument->delete();

        DashboardStatsCache::forget(request()->user());

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        return redirect()
            ->route('documents.index')
            ->with('status', __('documents.deleted_ok'));
    }
}
