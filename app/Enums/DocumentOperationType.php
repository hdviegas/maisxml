<?php

namespace App\Enums;

enum DocumentOperationType: int
{
    case Compras = 1;
    case Saida = 2;
    case Transferencia = 3;
    case EntradasGerais = 4;
    case Devolucoes = 5;

    public function label(): string
    {
        return match ($this) {
            self::Compras => __('documents.op_compras'),
            self::Saida => __('documents.op_saida'),
            self::Transferencia => __('documents.op_transferencia'),
            self::EntradasGerais => __('documents.op_entradas'),
            self::Devolucoes => __('documents.op_devolucoes'),
        };
    }
}
