<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadFiscalDocumentController extends Controller
{
    /**
     * @return StreamedResponse|Response
     */
    public function __invoke(FiscalDocument $fiscalDocument)
    {
        $this->authorize('view', $fiscalDocument);

        $disk = Storage::disk('local');
        if (! $disk->exists($fiscalDocument->storage_path)) {
            abort(404);
        }

        return $disk->download($fiscalDocument->storage_path, $fiscalDocument->chave.'.xml', [
            'Content-Type' => 'application/xml',
        ]);
    }
}
