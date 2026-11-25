<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\Documents\DocumentListFilters;
use App\Services\Documents\DocumentListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait InteractsWithFilteredDocuments
{
    protected function documentsQueryFromRequest(Request $request, bool $withSpedColumns = false): Builder
    {
        /** @var User $user */
        $user = $request->user();

        $query = DocumentListQuery::forUser($user);
        DocumentListQuery::applyFilters($query, DocumentListFilters::fromQueryArray($request->query()), $user);

        if ($withSpedColumns) {
            DocumentListQuery::withSpedColumns($query, $user);
        }

        return $query
            ->orderByDesc('xml.data_nf')
            ->orderByDesc('xml.id');
    }

    /**
     * Snapshot of list filters (parity with the documents table) for activity logs.
     *
     * @return array<string, mixed>
     */
    protected function auditFilterSnapshot(Request $request): array
    {
        return DocumentListFilters::fromQueryArray($request->query())->toAuditArray();
    }
}
