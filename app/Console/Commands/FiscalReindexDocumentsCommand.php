<?php

namespace App\Console\Commands;

use App\Models\FiscalDocument;
use App\Services\Cfop\OperationClassifier;
use App\Services\Nfe\NfeXmlParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FiscalReindexDocumentsCommand extends Command
{
    protected $signature = 'fiscal:reindex-documents {--organization= : organization_id (optional)}';

    protected $description = 'Re-lê XML armazenado e atualiza metadados (modelo, CFOP, operation_type, totais, etc.)';

    public function handle(NfeXmlParser $parser, OperationClassifier $classifier): int
    {
        $org = $this->option('organization');
        $query = FiscalDocument::query()->orderBy('id');
        if ($org !== null && $org !== '') {
            $query->where('organization_id', (int) $org);
        }

        $disk = Storage::disk('local');
        $updated = 0;
        $missing = 0;

        $query->chunkById(100, function ($docs) use ($disk, $parser, $classifier, &$updated, &$missing): void {
            foreach ($docs as $doc) {
                if (! $disk->exists($doc->storage_path)) {
                    $missing++;

                    continue;
                }
                $xml = $disk->get($doc->storage_path);
                try {
                    $parsed = $parser->parse($xml);
                } catch (\Throwable) {
                    continue;
                }

                $doc->update([
                    'modelo' => $parsed->modelo,
                    'serie' => $parsed->serie,
                    'numero' => $parsed->numero,
                    'issued_at' => $parsed->issuedAt,
                    'emitter_cnpj' => $parsed->emitterCnpj,
                    'recipient_doc' => $parsed->recipientDoc,
                    'total' => $parsed->total,
                    'cfop' => $parsed->cfop,
                    'operation_type' => $classifier->classify($parsed->cfop)?->value,
                ]);
                $updated++;
            }
        });

        $this->info("Atualizados: {$updated}; XML ausente: {$missing}");

        return self::SUCCESS;
    }
}
