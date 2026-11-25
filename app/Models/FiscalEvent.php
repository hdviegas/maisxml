<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FiscalEvent extends Model
{
    protected $table = 'fiscal_events';

    protected $fillable = [
        'organization_id',
        'chave',
        'tp_evento',
        'n_seq_evento',
        'dh_evento',
        'c_stat',
        'protocolo',
        'descricao',
        'x_correcao',
        'storage_path',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'tp_evento' => 'integer',
            'n_seq_evento' => 'integer',
            'dh_evento' => 'datetime',
        ];
    }

    /**
     * Registered cancellation events (110111 cancelamento, 110112 por substituição).
     *
     * @param  Builder<FiscalEvent>  $query
     * @return Builder<FiscalEvent>
     */
    public function scopeCancellations(Builder $query): Builder
    {
        return $query->whereIn('tp_evento', [110111, 110112])
            ->whereIn('c_stat', ['135', '136', '155']);
    }
}
