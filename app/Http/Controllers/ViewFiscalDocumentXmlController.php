<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ViewFiscalDocumentXmlController extends Controller
{
    public function __invoke(FiscalDocument $fiscalDocument): Response
    {
        $this->authorize('view', $fiscalDocument);

        $disk = Storage::disk('local');
        if (! $disk->exists($fiscalDocument->storage_path)) {
            abort(404);
        }

        $xml = $disk->get($fiscalDocument->storage_path);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$fiscalDocument->chave.'.xml"',
        ]);
    }
}
