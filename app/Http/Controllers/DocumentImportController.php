<?php

namespace App\Http\Controllers;

use App\Enums\OnDuplicateImport;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\Dashboard\DashboardStatsCache;
use App\Services\Nfe\NfeImportService;
use App\Services\Tenant\ActiveOrganizationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DocumentImportController extends Controller
{
    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $orgIds = ActiveOrganizationSession::scopedOrganizationIds($user);

        return view('import.index', [
            'branches' => Branch::query()
                ->whereIn('empresa', $orgIds->all())
                ->orderBy('social')
                ->get(),
        ]);
    }

    public function store(Request $request, NfeImportService $importService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:empresa_filial,codigo'],
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['file', 'max:10240'],
            'on_duplicate' => ['required', 'string', 'in:reject,skip,replace'],
        ], [], [
            'branch_id' => __('documents.branch'),
            'files' => __('documents.files'),
            'on_duplicate' => __('documents.on_duplicate'),
        ]);

        $branch = Branch::query()->findOrFail((int) $validated['branch_id']);
        if (! Organization::query()->forUser($user)->whereKey($branch->organization_id)->exists()) {
            return redirect()
                ->back()
                ->withErrors(['branch_id' => __('nfe.import.branch_forbidden')])
                ->withInput();
        }

        $mode = OnDuplicateImport::tryFrom($validated['on_duplicate']) ?? OnDuplicateImport::Reject;

        $ok = 0;
        /** @var list<string> $errorsList */
        $errorsList = [];
        foreach ((array) $request->file('files', []) as $file) {
            if ($file === null) {
                continue;
            }
            try {
                $importService->importForBranch($user, $branch, $file->get(), $mode);
                $ok++;
            } catch (\InvalidArgumentException $e) {
                $errorsList[] = $file->getClientOriginalName().': '.__('nfe.import.'.$e->getMessage());
            } catch (RuntimeException $e) {
                $errorsList[] = $file->getClientOriginalName().': '.__('nfe.import.'.$e->getMessage());
            }
        }

        if ($ok > 0) {
            DashboardStatsCache::forget($user);
        }

        $flash = redirect()->route('import.index');
        if ($ok > 0) {
            $flash = $flash->with(
                'import_ok',
                trans_choice('documents.imported_count', $ok, ['count' => $ok])
            );
        }
        if ($errorsList !== []) {
            $flash = $flash->with([
                'import_warn' => __('nfe.import.partial_errors'),
                'import_errors_list' => $errorsList,
            ]);
        }

        return $flash;
    }
}
