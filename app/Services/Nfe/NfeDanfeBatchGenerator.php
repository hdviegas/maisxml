<?php

namespace App\Services\Nfe;

use App\Models\FiscalDocument;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Junta as DANFEs/DANFCEs de varios documentos num unico PDF.
 *
 * Cada documento e renderizado isoladamente pelo sped-da (que nao permite acumular notas
 * no mesmo objeto de forma limpa) e as paginas sao mescladas com FPDI, preservando o
 * tamanho de cada pagina (A4 para NF-e, cupom estreito para NFC-e). PHP puro, sem binarios.
 */
class NfeDanfeBatchGenerator
{
    public function __construct(private readonly NfeDanfeGenerator $danfeGenerator) {}

    /**
     * @param  iterable<FiscalDocument>  $documents  na ordem desejada
     * @return array{pdf: ?string, included: list<string>, skipped: list<string>} chaves incluidas/puladas
     */
    public function merge(iterable $documents, Filesystem $disk): array
    {
        $fpdi = new Fpdi;
        $fpdi->SetAutoPageBreak(false);
        $included = [];
        $skipped = [];

        foreach ($documents as $doc) {
            $chave = (string) $doc->chave;

            try {
                if (! $disk->exists($doc->storage_path)) {
                    throw new \RuntimeException('xml ausente');
                }

                $xml = $disk->get($doc->storage_path);
                $modelo = $this->danfeGenerator->detectIdeModFromXml($xml) ?? $doc->modelo;
                $binary = $this->danfeGenerator->pdfBinary($xml, $modelo);

                $this->appendPages($fpdi, $binary);
                $included[] = $chave;
            } catch (Throwable $e) {
                $skipped[] = $chave;
                Log::warning('Lote DANFE: documento pulado.', ['chave' => $chave, 'erro' => $e->getMessage()]);
            }
        }

        return [
            'pdf' => $included === [] ? null : $fpdi->Output('S'),
            'included' => $included,
            'skipped' => $skipped,
        ];
    }

    private function appendPages(Fpdi $fpdi, string $binary): void
    {
        // Importa tudo antes de alterar $fpdi: falha no meio nao deixa paginas parciais.
        $count = $fpdi->setSourceFile(StreamReader::createByString($binary));
        $templates = [];
        for ($page = 1; $page <= $count; $page++) {
            $id = $fpdi->importPage($page);
            $templates[] = [$id, $fpdi->getTemplateSize($id)];
        }

        foreach ($templates as [$id, $size]) {
            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($id);
        }
    }
}
