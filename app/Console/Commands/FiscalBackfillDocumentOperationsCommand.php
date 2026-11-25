<?php

namespace App\Console\Commands;

use App\Models\FiscalDocument;
use App\Services\Cfop\OperationClassifier;
use Illuminate\Console\Command;

class FiscalBackfillDocumentOperationsCommand extends Command
{
    protected $signature = 'fiscal:backfill-operations';

    protected $description = 'Recalcula operation_type (legado operacao 1–5) a partir do CFOP usando config/cfop.php';

    public function handle(OperationClassifier $classifier): int
    {
        $updated = 0;
        FiscalDocument::query()->orderBy('id')->chunkById(200, function ($documents) use ($classifier, &$updated): void {
            foreach ($documents as $doc) {
                $next = $classifier->classify($doc->cfop)?->value;
                if ($next !== $doc->operation_type?->value) {
                    $doc->update(['operation_type' => $next]);
                    $updated++;
                }
            }
        });

        $this->info("Atualizados: {$updated}");

        return self::SUCCESS;
    }
}
