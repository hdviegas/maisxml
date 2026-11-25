<?php

namespace App\Services\Cfop;

use App\Enums\DocumentOperationType;

class OperationClassifier
{
    public function classify(?string $cfop): ?DocumentOperationType
    {
        if ($cfop === null || $cfop === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $cfop) ?? '';
        if (strlen($digits) < 4) {
            return null;
        }

        $code = substr($digits, 0, 4);

        $cfg = config('cfop', []);

        if ($this->inList($code, $cfg['devolucoes'] ?? [])) {
            return DocumentOperationType::Devolucoes;
        }

        if ($this->inList($code, $cfg['transferencia'] ?? [])) {
            return DocumentOperationType::Transferencia;
        }

        if ($this->inList($code, $cfg['saida'] ?? [])) {
            return DocumentOperationType::Saida;
        }

        if ($this->inList($code, $cfg['compras'] ?? [])) {
            return DocumentOperationType::Compras;
        }

        if (! ($cfg['fallback_enabled'] ?? false)) {
            return DocumentOperationType::EntradasGerais;
        }

        $first = $code[0];

        return match ($first) {
            '1', '2', '3' => DocumentOperationType::Compras,
            '5', '6', '7' => DocumentOperationType::Saida,
            default => DocumentOperationType::EntradasGerais,
        };
    }

    /**
     * @param  list<string>  $list
     */
    private function inList(string $code, array $list): bool
    {
        foreach ($list as $item) {
            if ((string) $item === $code) {
                return true;
            }
        }

        return false;
    }
}
