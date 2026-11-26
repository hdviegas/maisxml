<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\DashboardStatsCache;
use App\Services\Documents\DocumentListFilters;
use App\Services\Documents\DocumentListQuery;
use App\Support\IssuedPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        [$issuedFrom, $issuedTo] = $this->resolveIssuedPeriod($request);

        $listQuery = array_filter([
            'issued_from' => $issuedFrom,
            'issued_to' => $issuedTo,
        ], fn ($v) => $v !== null && $v !== '');

        $ttl = (int) config('dashboard.stats_cache_ttl', 180);
        $stats = $ttl > 0
            ? Cache::remember(
                DashboardStatsCache::cacheKey($user, $issuedFrom, $issuedTo),
                $ttl,
                fn () => $this->aggregateStats($user, $issuedFrom, $issuedTo),
            )
            : $this->aggregateStats($user, $issuedFrom, $issuedTo);

        $timezone = (string) config('app.timezone', 'UTC');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return view('dashboard', [
            'documentsTotal' => $stats['documentsTotal'],
            'spedDiffCount' => $stats['spedDiffCount'],
            'spedMissingCount' => $stats['spedMissingCount'],
            'issuedFrom' => $issuedFrom,
            'issuedTo' => $issuedTo,
            'periodPresets' => $this->periodPresets($today),
            'documentsIndexUrl' => route('documents.index', $listQuery),
            'documentsSpedDiffUrl' => route('documents.index', ['sped_reconcile' => 'diff'] + $listQuery),
            'documentsSpedMissingUrl' => route('documents.index', ['sped_reconcile' => 'none'] + $listQuery),
            'importUrl' => route('import.index'),
            'reportsUrl' => route('reports.index'),
        ]);
    }

    /**
     * @return array{0: string, 1: string} [issued_from, issued_to] as Y-m-d
     */
    private function resolveIssuedPeriod(Request $request): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $defaultDays = max(1, (int) config('dashboard.default_period_days', 30));
        $maxSpan = max($defaultDays, (int) config('dashboard.max_period_days', 366));

        [$defaultFrom, $defaultTo] = IssuedPeriod::defaultRange();

        $rawFrom = $request->query('issued_from');
        $rawTo = $request->query('issued_to');

        if (! is_string($rawFrom) || ! is_string($rawTo) || $rawFrom === '' || $rawTo === '') {
            return [$defaultFrom, $defaultTo];
        }

        try {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $rawFrom, $timezone)->startOfDay();
            $to = CarbonImmutable::createFromFormat('Y-m-d', $rawTo, $timezone)->startOfDay();
        } catch (\Throwable) {
            return [$defaultFrom, $defaultTo];
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $spanDays = $from->diffInDays($to) + 1;
        if ($spanDays > $maxSpan) {
            $from = $to->subDays($maxSpan - 1);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * @return list<array{days: int, issued_from: string, issued_to: string}>
     */
    private function periodPresets(CarbonImmutable $today): array
    {
        $out = [];
        foreach ([7, 30, 90] as $days) {
            $out[] = [
                'days' => $days,
                'issued_from' => $today->subDays($days - 1)->toDateString(),
                'issued_to' => $today->toDateString(),
            ];
        }

        return $out;
    }

    /**
     * @return array{documentsTotal: int, spedDiffCount: int, spedMissingCount: int}
     */
    private function aggregateStats(User $user, string $issuedFrom, string $issuedTo): array
    {
        $period = new DocumentListFilters(issuedFrom: $issuedFrom, issuedTo: $issuedTo);

        $base = DocumentListQuery::forUser($user);
        DocumentListQuery::applyFilters($base, $period, $user);
        $documentsTotal = (clone $base)->count();

        $diffQuery = DocumentListQuery::forUser($user);
        DocumentListQuery::applyFilters(
            $diffQuery,
            new DocumentListFilters(issuedFrom: $issuedFrom, issuedTo: $issuedTo, spedReconcile: 'diff'),
            $user,
        );
        $spedDiffCount = $diffQuery->count();

        $noneQuery = DocumentListQuery::forUser($user);
        DocumentListQuery::applyFilters(
            $noneQuery,
            new DocumentListFilters(issuedFrom: $issuedFrom, issuedTo: $issuedTo, spedReconcile: 'none'),
            $user,
        );
        $spedMissingCount = $noneQuery->count();

        return [
            'documentsTotal' => $documentsTotal,
            'spedDiffCount' => $spedDiffCount,
            'spedMissingCount' => $spedMissingCount,
        ];
    }
}
