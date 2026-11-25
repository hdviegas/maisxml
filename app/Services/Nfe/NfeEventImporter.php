<?php

namespace App\Services\Nfe;

use App\Models\FiscalDocument;
use App\Models\FiscalEvent;
use App\Models\Organization;
use App\Services\Audit\ActivityLogService;
use App\Support\Tenant\Cnpj;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Stores NF-e events (cancellation, CC-e, ...) and applies cancellations to `xml.cancelada`.
 *
 * Limitation: when the organization is only the RECIPIENT of an inbound note and that note
 * has not been imported yet, an event authored by the supplier cannot be attributed to the
 * organization and is dropped ('not_owned'). The SIEG sync downloads notes and events
 * together, so the note normally arrives first.
 */
class NfeEventImporter
{
    public function __construct(
        protected NfeEventParser $parser,
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * @return 'created'|'duplicate'|'not_owned'
     */
    public function import(int $organizationId, string $xml, string $source = 'sieg'): string
    {
        $event = $this->parser->parse($xml);
        $orgDigits = Organization::cnpjDigitsFor($organizationId);

        $document = FiscalDocument::query()->where('chave', $event->chave)->first();

        if ($document !== null) {
            $emit = Cnpj::digits((string) $document->getRawOriginal('cnpj_emit'));
            $dest = Cnpj::digits((string) $document->getRawOriginal('cnpj_dest'));
            $owned = in_array($emit, $orgDigits, true) || in_array($dest, $orgDigits, true);
        } else {
            $embedded = substr($event->chave, 6, 14);
            $author = Cnpj::digits($event->authorDoc ?? '');
            $candidates = array_filter([
                $embedded,
                ltrim($embedded, '0'),
                $author,
                $author !== '' ? str_pad($author, 14, '0', STR_PAD_LEFT) : '',
            ], fn ($v) => $v !== '');
            $owned = false;
            foreach ($candidates as $c) {
                if (in_array($c, $orgDigits, true) || in_array(ltrim($c, '0'), array_map(fn ($d) => ltrim($d, '0'), $orgDigits), true)) {
                    $owned = true;
                    break;
                }
            }
        }

        if (! $owned) {
            return 'not_owned';
        }

        $exists = FiscalEvent::query()
            ->where('chave', $event->chave)
            ->where('tp_evento', $event->tpEvento)
            ->where('n_seq_evento', $event->nSeqEvento)
            ->exists();
        if ($exists) {
            return 'duplicate';
        }

        $relative = "eventos/{$event->chave}/{$event->tpEvento}-{$event->nSeqEvento}.xml";
        $disk = Storage::disk('local');
        if (! $disk->put($relative, $xml)) {
            throw new RuntimeException('storage_failed');
        }

        try {
            FiscalEvent::query()->create([
                'organization_id' => $organizationId,
                'chave' => $event->chave,
                'tp_evento' => $event->tpEvento,
                'n_seq_evento' => $event->nSeqEvento,
                'dh_evento' => $event->dhEvento,
                'c_stat' => $event->cStat,
                'protocolo' => $event->protocolo,
                'descricao' => $event->descricao !== null ? mb_substr($event->descricao, 0, 120) : null,
                'x_correcao' => $event->xCorrecao,
                'storage_path' => $relative,
                'source' => $source,
            ]);
        } catch (QueryException $e) {
            // Lost a race against a concurrent import of the same event: the winner owns the file.
            if (FiscalEvent::query()->where('chave', $event->chave)
                ->where('tp_evento', $event->tpEvento)
                ->where('n_seq_evento', $event->nSeqEvento)->exists()) {
                return 'duplicate';
            }
            $disk->delete($relative);
            throw $e;
        }

        if ($document !== null && $event->isCancellation() && ! $document->cancelada) {
            $document->forceFill(['cancelada' => true])->save();
        }

        $this->activityLog->record(
            null,
            'fiscal_event.imported',
            $document,
            [
                'via' => $source,
                'chave' => $event->chave,
                'tp_evento' => $event->tpEvento,
                'n_seq_evento' => $event->nSeqEvento,
            ],
            $organizationId,
        );

        return 'created';
    }
}
