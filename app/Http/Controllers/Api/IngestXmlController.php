<?php

namespace App\Http\Controllers\Api;

use App\Enums\OnDuplicateImport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Nfe\NfeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class IngestXmlController extends Controller
{
    public function __invoke(Request $request, NfeImportService $importService): JsonResponse
    {
        $branchId = $request->header('X-Branch-Id');
        if ($branchId === null || $branchId === '') {
            $branchId = $request->query('branch_id');
        }

        if ($branchId === null || $branchId === '' || ! ctype_digit((string) $branchId)) {
            return response()->json(['message' => __('ingest.branch_required')], 422);
        }

        $branch = Branch::query()->find((int) $branchId);
        if ($branch === null) {
            return response()->json(['message' => __('ingest.branch_not_found')], 422);
        }

        $body = $request->getContent();
        if ($body === '' || $body === false) {
            return response()->json(['message' => __('nfe.import.empty_xml')], 422);
        }

        $hdr = strtolower((string) $request->header('X-On-Duplicate', 'reject'));
        $onDuplicate = OnDuplicateImport::tryFrom($hdr) ?? OnDuplicateImport::Reject;

        try {
            $outcome = $importService->importViaIngest($branch, $body, $onDuplicate);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => __('nfe.import.'.$e->getMessage())], 422);
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

        return response()->json($payload, $code);
    }
}
