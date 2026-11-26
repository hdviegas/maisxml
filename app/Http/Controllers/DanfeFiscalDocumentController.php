<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use App\Services\Audit\ActivityLogService;
use App\Services\Nfe\NfeDanfeGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DanfeFiscalDocumentController extends Controller
{
    public function __invoke(
        FiscalDocument $fiscalDocument,
        NfeDanfeGenerator $danfeGenerator,
        ActivityLogService $activityLog,
    ): Response {
        $this->authorize('view', $fiscalDocument);

        $disk = Storage::disk('local');
        if (! $disk->exists($fiscalDocument->storage_path)) {
            abort(404);
        }

        $xml = $disk->get($fiscalDocument->storage_path);

        $modelo = $danfeGenerator->detectIdeModFromXml($xml) ?? $fiscalDocument->modelo;

        try {
            $pdf = $danfeGenerator->pdfBinary($xml, $modelo);
        } catch (Throwable) {
            abort(422, 'Não foi possível gerar a DANFE deste documento.');
        }

        $activityLog->record(
            request()->user(),
            'documents.danfe_pdf',
            $fiscalDocument,
            ['chave' => $fiscalDocument->chave],
        );

        $filename = 'DANFE-'.$fiscalDocument->chave.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
