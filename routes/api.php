<?php

use App\Http\Controllers\Api\DocumentDanfeController;
use App\Http\Controllers\Api\DocumentIndexController;
use App\Http\Controllers\Api\DocumentShowController;
use App\Http\Controllers\Api\DocumentStoreController;
use App\Http\Controllers\Api\IngestXmlController;
use App\Http\Middleware\ShareTenantLogContext;
use Illuminate\Support\Facades\Route;

Route::post('v1/ingest/xml', IngestXmlController::class)
    ->middleware(['ingest.auth', 'throttle:ingest'])
    ->name('api.v1.ingest.xml');

Route::middleware(['auth:sanctum', ShareTenantLogContext::class])->prefix('v1')->group(function (): void {
    Route::get('documents', [DocumentIndexController::class, 'index'])->name('api.v1.documents.index');
    Route::post('documents', [DocumentStoreController::class, 'store'])->name('api.v1.documents.store');
    Route::get('documents/{fiscalDocument}/danfe', DocumentDanfeController::class)->name('api.v1.documents.danfe');
    Route::get('documents/{fiscalDocument}', [DocumentShowController::class, 'show'])->name('api.v1.documents.show');
});
