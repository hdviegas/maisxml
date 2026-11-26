<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\Documents\DocumentListFilters;
use App\Services\Documents\DocumentListQuery;
use App\Support\IssuedPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait InteractsWithFilteredDocuments
{
    protected function documentsQueryFromRequest(Request $request, bool $withSpedColumns = false): Builder
    {
        /** @var User $user */
        $user = $request->user();

        $query = DocumentListQuery::forUser($user);
        DocumentListQuery::applyFilters($query, $this->documentFilters($request), $user);

        if ($withSpedColumns) {
            DocumentListQuery::withSpedColumns($query, $user);
        }

        return $query
            ->orderByDesc('xml.data_nf')
            ->orderByDesc('xml.id');
    }

    /**
     * Request filters with the default issue period applied when no usable period was sent
     * (and the search is not a full 44-digit key). Keeps /documentos, exports and the API
     * from scanning the whole history by accident.
     */
    protected function documentFilters(Request $request): DocumentListFilters
    {
        $filters = DocumentListFilters::fromQueryArray($request->query());

        $hasPeriod = IssuedPeriod::parseDate($filters->issuedFrom) !== null
            || IssuedPeriod::parseDate($filters->issuedTo) !== null;

        if (! $hasPeriod && ! $filters->isFullKeySearch()) {
            [$filters->issuedFrom, $filters->issuedTo] = IssuedPeriod::defaultRange();
        }

        return $filters;
    }

    /**
     * Snapshot of list filters (parity with the documents table) for activity logs.
     *
     * @return array<string, mixed>
     */
    protected function auditFilterSnapshot(Request $request): array
    {
        return $this->documentFilters($request)->toAuditArray();
    }
}
