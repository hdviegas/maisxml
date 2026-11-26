<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\Dashboard\DashboardStatsCache;
use App\Services\Sped\SpedC100ImportService;
use App\Services\Tenant\ActiveOrganizationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpedImportController extends Controller
{
    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $orgIds = ActiveOrganizationSession::scopedOrganizationIds($user);

        return view('sped.index', [
            'branches' => Branch::query()
                ->whereIn('empresa', $orgIds->all())
                ->get()
                ->sortBy(fn (Branch $b) => mb_strtolower($b->name))
                ->values(),
        ]);
    }

    public function store(Request $request, SpedC100ImportService $importService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:empresa_filial,codigo'],
            'file' => ['required', 'file', 'max:51200'],
        ], [], [
            'branch_id' => __('sped.branch'),
            'file' => __('sped.file'),
        ]);

        $branch = Branch::query()->findOrFail((int) $validated['branch_id']);

        if (! Organization::query()->forUser($user)->whereKey($branch->organization_id)->exists()) {
            return redirect()
                ->back()
                ->withErrors(['branch_id' => __('nfe.import.branch_forbidden')])
                ->withInput();
        }

        $text = (string) $request->file('file')->get();

        try {
            $n = $importService->importFromText($user, $branch, $text);
        } catch (\InvalidArgumentException $e) {
            $msg = match ($e->getMessage()) {
                'branch_forbidden' => __('nfe.import.branch_forbidden'),
                'empty_sped' => __('sped.empty_file'),
                default => __('sped.import_error'),
            };

            return redirect()->back()->withErrors(['file' => $msg])->withInput();
        }

        if ($n > 0) {
            DashboardStatsCache::forget($user);
        }

        return redirect()->route('sped.index')->with(
            'sped_ok',
            trans_choice('sped.lines_imported', $n, ['count' => $n])
        );
    }
}
