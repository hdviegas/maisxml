<?php

namespace App\Http\Controllers\Api;

use App\Enums\OnDuplicateImport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Services\Dashboard\DashboardStatsCache;
use App\Services\Nfe\NfeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DocumentStoreController extends Controller
{
    public function store(Request $request, NfeImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:empresa_filial,codigo'],
            'xml' => ['required_without:file', 'nullable', 'string'],
            'file' => ['required_without:xml', 'nullable', 'file', 'max:10240'],
            'on_duplicate' => ['sometimes', 'string', 'in:reject,skip,replace'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        if (! Organization::query()->forUser($request->user())->whereKey($branch->organization_id)->exists()) {
            return response()->json(['message' => __('nfe.import.branch_forbidden')], 403);
        }

        $body = $validated['xml'] ?? $request->file('file')->get();

        $onDuplicate = OnDuplicateImport::tryFrom($validated['on_duplicate'] ?? 'reject')
            ?? OnDuplicateImport::Reject;

        try {
            $outcome = $importService->importForBranch($request->user(), $branch, $body, $onDuplicate);
        } catch (\InvalidArgumentException $e) {
            $status = $e->getMessage() === 'branch_forbidden' ? 403 : 422;

            return response()->json(['message' => __('nfe.import.'.$e->getMessage())], $status);
        } catch (RuntimeException $e) {
            return response()->json(['message' => __('nfe.import.'.$e->getMessage())], 409);
        }

        $payload = [
            'id' => $outcome->document->id,
            'chave' => $outcome->document->chave,
            'organization_id' => $outcome->document->organization_id,
            'branch_id' => $outcome->document->branch_id,
            'result' => $outcome->result,
        ];

        $code = $outcome->wasCreated() ? 201 : 200;

        DashboardStatsCache::forget($request->user());

        return response()->json($payload, $code);
    }
}
