<?php

namespace App\Services\Nfe;

use App\Enums\OnDuplicateImport;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\FiscalEvent;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\ActivityLogService;
use App\Services\Cfop\OperationClassifier;
use App\Support\Tenant\Cnpj;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class NfeImportService
{
    public function __construct(
        protected NfeXmlParser $parser,
        protected OperationClassifier $operationClassifier,
        protected ActivityLogService $activityLog,
    ) {}

    public function importForBranch(
        User $user,
        Branch $branch,
        string $xmlBody,
        OnDuplicateImport $onDuplicate = OnDuplicateImport::Reject,
    ): NfeImportOutcome {
        $branchOrgId = (int) $branch->empresa;
        if (! Organization::query()->forUser($user)->whereKey($branchOrgId)->exists()) {
            throw new InvalidArgumentException('branch_forbidden');
        }

        return $this->persist($branch, $xmlBody, $user->id, $onDuplicate, 'user');
    }

    public function importViaIngest(
        Branch $branch,
        string $xmlBody,
        OnDuplicateImport $onDuplicate = OnDuplicateImport::Reject,
    ): NfeImportOutcome {
        return $this->persist($branch, $xmlBody, null, $onDuplicate, 'ingest');
    }

    /**
     * Import on behalf of an organization with no acting user (e.g. the SIEG sync).
     * Picks the organization's branch matching the recipient (preferred) or the emitter.
     */
    public function importForOrganization(
        int $organizationId,
        string $xmlBody,
        OnDuplicateImport $onDuplicate = OnDuplicateImport::Skip,
        string $via = 'sieg',
    ): NfeImportOutcome {
        $parsed = $this->parser->parse($xmlBody);
        $branches = Branch::query()->where('empresa', $organizationId)->get();

        $pick = function (string $doc) use ($branches): ?Branch {
            if ($doc === '') {
                return null;
            }

            return $branches->first(fn (Branch $b) => Cnpj::digits((string) $b->cnpj) === $doc);
        };

        $branch = $pick(Cnpj::digits($parsed->recipientDoc ?? ''))
            ?? $pick(Cnpj::digits($parsed->emitterCnpj ?? ''));
        if ($branch === null) {
            throw new InvalidArgumentException('xml_not_for_organization');
        }

        return $this->persist($branch, $xmlBody, null, $onDuplicate, $via);
    }

    private function persist(
        Branch $branch,
        string $xmlBody,
        ?int $importedBy,
        OnDuplicateImport $onDuplicate,
        string $via = 'ingest',
    ): NfeImportOutcome {
        $parsed = $this->parser->parse($xmlBody);

        $this->assertChaveMatchesEmitter($parsed);

        $branchOrgId = (int) $branch->empresa;
        $orgDigits = Organization::cnpjDigitsFor($branchOrgId);

        $emit = Cnpj::digits($parsed->emitterCnpj ?? '');
        $dest = Cnpj::digits($parsed->recipientDoc ?? '');
        if (! in_array($emit, $orgDigits, true) && ! in_array($dest, $orgDigits, true)) {
            throw new InvalidArgumentException('xml_not_for_organization');
        }

        $existing = FiscalDocument::query()->where('chave', $parsed->chave)->first();

        if ($existing !== null) {
            $existingEmit = Cnpj::digits((string) $existing->getRawOriginal('cnpj_emit'));
            $existingDest = Cnpj::digits((string) $existing->getRawOriginal('cnpj_dest'));
            if (! in_array($existingEmit, $orgDigits, true) && ! in_array($existingDest, $orgDigits, true)) {
                throw new RuntimeException('duplicate_chave_other_org');
            }

            return match ($onDuplicate) {
                OnDuplicateImport::Reject => throw new RuntimeException('duplicate_chave'),
                OnDuplicateImport::Skip => new NfeImportOutcome($existing, 'skipped'),
                OnDuplicateImport::Replace => new NfeImportOutcome(
                    $this->replaceDocument($existing, $branch, $xmlBody, $parsed, $importedBy, $via),
                    'replaced'
                ),
            };
        }

        return new NfeImportOutcome(
            $this->createDocument($branch, $xmlBody, $parsed, $importedBy, $via),
            'created'
        );
    }

    /**
     * The access key embeds the emitter document at positions 7-20 (CPF is zero-padded).
     */
    private function assertChaveMatchesEmitter(NfeParsedData $parsed): void
    {
        $emit = Cnpj::digits($parsed->emitterCnpj ?? '');
        if ($emit === '') {
            return;
        }

        if (substr($parsed->chave, 6, 14) !== str_pad($emit, 14, '0', STR_PAD_LEFT)) {
            throw new InvalidArgumentException('chave_emitter_mismatch');
        }
    }

    private function createDocument(
        Branch $branch,
        string $xmlBody,
        NfeParsedData $parsed,
        ?int $importedBy,
        string $via = 'ingest',
    ): FiscalDocument {
        $emitter = Cnpj::digits($parsed->emitterCnpj ?? '');
        $dataFolder = $parsed->issuedAt !== null
            ? $parsed->issuedAt->format('m-y')
            : now()->format('m-y');
        $filename = $parsed->chave.'.xml';
        $relative = "xml/{$emitter}/{$dataFolder}/{$filename}";

        if (! Storage::disk('local')->put($relative, $xmlBody)) {
            throw new RuntimeException('storage_failed');
        }

        $extra = [];
        if (Schema::hasColumn('xml', 'operation_type')) {
            $extra['operation_type'] = $this->operationClassifier->classify($parsed->cfop)?->value;
        }

        try {
            $doc = FiscalDocument::query()->create(array_merge([
                'url' => $relative,
                'chave' => $parsed->chave,
                'empresa' => $emitter,
                'social' => null,
                'dest' => $parsed->recipientDoc,
                'data_nf' => $parsed->issuedAt,
                'ip' => request()->ip(),
                'cnpj_emit' => $parsed->emitterCnpj,
                'cnpj_dest' => $parsed->recipientDoc,
                'social_emit' => null,
                'social_dest' => null,
                'numero_nf' => $parsed->numero,
                'modelo_nf' => $parsed->modelo,
                'serie_nf' => $parsed->serie,
                'total_nf' => $parsed->total,
                'total_icms' => null,
                'total_icms_st' => null,
                'total_frete' => null,
                'cfop_nf' => $parsed->cfop,
                'cancelada' => $this->isCancelledStat($parsed->cStat),
            ], $extra));

            $this->applyCancellationEvents($doc);

            $this->activityLog->record(
                $importedBy !== null ? User::query()->find($importedBy) : null,
                'fiscal_document.imported',
                $doc,
                ['via' => $via],
            );

            return $doc;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($relative);
            throw $e;
        }
    }

    private function replaceDocument(
        FiscalDocument $existing,
        Branch $branch,
        string $xmlBody,
        NfeParsedData $parsed,
        ?int $importedBy,
        string $via = 'ingest',
    ): FiscalDocument {
        $disk = Storage::disk('local');
        $oldPath = $existing->storage_path;
        $emitter = Cnpj::digits($parsed->emitterCnpj ?? '');
        $dataFolder = $parsed->issuedAt !== null
            ? $parsed->issuedAt->format('m-y')
            : now()->format('m-y');
        $relative = "xml/{$emitter}/{$dataFolder}/{$parsed->chave}.xml";

        if (! $disk->put($relative, $xmlBody)) {
            throw new RuntimeException('storage_failed');
        }

        $extra = [];
        if (Schema::hasColumn('xml', 'operation_type')) {
            $extra['operation_type'] = $this->operationClassifier->classify($parsed->cfop)?->value;
        }

        try {
            $existing->update(array_merge([
                'url' => $relative,
                'empresa' => $emitter,
                'social' => null,
                'dest' => $parsed->recipientDoc,
                'data_nf' => $parsed->issuedAt,
                'cnpj_emit' => $parsed->emitterCnpj,
                'cnpj_dest' => $parsed->recipientDoc,
                'social_emit' => null,
                'social_dest' => null,
                'numero_nf' => $parsed->numero,
                'modelo_nf' => $parsed->modelo,
                'serie_nf' => $parsed->serie,
                'total_nf' => $parsed->total,
                'total_icms' => null,
                'total_icms_st' => null,
                'total_frete' => null,
                'cfop_nf' => $parsed->cfop,
                'cancelada' => $this->isCancelledStat($parsed->cStat),
            ], $extra));

            if ($oldPath !== $relative && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }

            $doc = $existing->fresh();
            $this->applyCancellationEvents($doc);
            $this->activityLog->record(
                $importedBy !== null ? User::query()->find($importedBy) : null,
                'fiscal_document.replaced',
                $doc,
                ['via' => $via],
            );

            return $doc;
        } catch (\Throwable $e) {
            if ($disk->exists($relative)) {
                $disk->delete($relative);
            }
            throw $e;
        }
    }

    private function isCancelledStat(?string $cStat): bool
    {
        return in_array($cStat, ['101', '151'], true);
    }

    /**
     * A cancellation event may have been stored before the note itself arrived.
     */
    private function applyCancellationEvents(FiscalDocument $doc): void
    {
        if ($doc->cancelada || ! Schema::hasTable('fiscal_events')) {
            return;
        }

        if (FiscalEvent::query()->cancellations()->where('chave', $doc->chave)->exists()) {
            $doc->forceFill(['cancelada' => true])->save();
        }
    }
}
