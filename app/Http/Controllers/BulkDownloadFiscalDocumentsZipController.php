<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use App\Services\Audit\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BulkDownloadFiscalDocumentsZipController extends Controller
{
    public function __invoke(Request $request, ActivityLogService $activityLog): BinaryFileResponse
    {
        $this->authorize('viewAny', FiscalDocument::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer', 'exists:xml,id'],
        ]);

        $ids = $validated['ids'];
        $docs = FiscalDocument::query()->whereIn('id', $ids)->get();

        foreach ($docs as $doc) {
            $this->authorize('view', $doc);
        }

        $disk = Storage::disk('local');
        $missing = $docs->first(fn (FiscalDocument $d) => ! $disk->exists($d->storage_path));
        if ($missing !== null) {
            abort(404, 'Arquivo XML não encontrado no armazenamento.');
        }

        $activityLog->record(
            $request->user(),
            'documents.bulk_zip',
            null,
            ['ids' => $ids, 'count' => $docs->count()],
            $docs->first()->organization_id,
        );

        $tempDir = storage_path('app/tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir.'/'.Str::uuid().'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível gerar o arquivo ZIP.');
        }

        foreach ($docs as $doc) {
            $zip->addFile($disk->path($doc->storage_path), $doc->chave.'.xml');
        }

        $zip->close();

        return response()->download($zipPath, 'documentos_xml.zip')->deleteFileAfterSend(true);
    }
}
