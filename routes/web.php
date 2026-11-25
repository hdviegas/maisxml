<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BulkDownloadFiscalDocumentsZipController;
use App\Http\Controllers\DanfeFiscalDocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DestroyFiscalDocumentController;
use App\Http\Controllers\DocumentImportController;
use App\Http\Controllers\DocumentsIndexController;
use App\Http\Controllers\DownloadFiscalDocumentController;
use App\Http\Controllers\ExportDocumentsCsvController;
use App\Http\Controllers\ExportDocumentsPdfController;
use App\Http\Controllers\LookupFiscalDocumentByChaveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsPageController;
use App\Http\Controllers\ScopeOrganizationController;
use App\Http\Controllers\SettingsPageController;
use App\Http\Controllers\SpedImportController;
use App\Http\Controllers\ViewFiscalDocumentXmlController;
use App\Http\Middleware\ShareTenantLogContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', ShareTenantLogContext::class])->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('scope/organization', ScopeOrganizationController::class)->name('scope.organization');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('documentos', DocumentsIndexController::class)->name('documents.index');
    Route::get('documentos/por-chave/{chave}', LookupFiscalDocumentByChaveController::class)
        ->where('chave', '[0-9]{44}')
        ->name('documents.lookup_chave');
    Route::delete('documentos/{fiscalDocument}', DestroyFiscalDocumentController::class)->name('documents.destroy');
    Route::get('documentos/exportar/csv', ExportDocumentsCsvController::class)->name('documents.export.csv');
    Route::get('documentos/exportar/pdf', ExportDocumentsPdfController::class)->name('documents.export.pdf');
    Route::post('documentos/baixar-zip', BulkDownloadFiscalDocumentsZipController::class)->name('documents.bulk_zip');
    Route::get('documentos/{fiscalDocument}/xml', ViewFiscalDocumentXmlController::class)->name('documents.view_xml');
    Route::get('documentos/{fiscalDocument}/danfe', DanfeFiscalDocumentController::class)->name('documents.danfe');
    Route::get('documentos/{fiscalDocument}/baixar', DownloadFiscalDocumentController::class)
        ->name('documents.download');

    Route::get('importar', [DocumentImportController::class, 'create'])->name('import.index');
    Route::post('importar', [DocumentImportController::class, 'store'])->name('import.store');

    Route::get('relatorios', ReportsPageController::class)->name('reports.index');

    Route::get('sped', [SpedImportController::class, 'create'])->name('sped.index');
    Route::post('sped', [SpedImportController::class, 'store'])->name('sped.store');

    Route::view('manifestacao', 'pages.manifestacao')->name('manifestacao.index');

    Route::get('configuracoes', SettingsPageController::class)->name('settings.index');
});

require __DIR__.'/auth.php';
