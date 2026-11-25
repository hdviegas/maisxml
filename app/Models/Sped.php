<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy SPED C100 cross-check rows (`sped` table).
 */
class Sped extends Model
{
    protected $table = 'sped';

    public $timestamps = false;

    protected $fillable = [
        'cnpj',
        'data_nf',
        'chave_nf',
        'total_nf',
        'total_icms',
    ];

    protected function casts(): array
    {
        return [
            'data_nf' => 'date',
            'total_nf' => 'decimal:2',
            'total_icms' => 'decimal:2',
        ];
    }
}
