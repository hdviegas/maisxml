<?php

namespace App\Http\Controllers;

use App\Services\Documents\DocumentListQuery;
use Illuminate\Http\RedirectResponse;

class LookupFiscalDocumentByChaveController extends Controller
{
    public function __invoke(string $chave): RedirectResponse
    {
        $safe = preg_replace('/\D/', '', $chave) ?? '';
        if (strlen($safe) !== 44) {
            abort(404);
        }

        $doc = DocumentListQuery::forUser(auth()->user())
            ->where('xml.chave', $safe)
            ->first();

        if ($doc === null) {
            abort(404);
        }

        $this->authorize('view', $doc);

        return redirect()->route('documents.index', ['q' => $safe]);
    }
}
