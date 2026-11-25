<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiegSyncCursor extends Model
{
    protected $table = 'sieg_sync_cursors';

    protected $fillable = [
        'sieg_account_id', 'cnpj', 'role', 'tipo_xml', 'window_start', 'window_end', 'skip', 'synced_until',
    ];

    protected function casts(): array
    {
        return [
            'tipo_xml' => 'integer',
            'skip' => 'integer',
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'synced_until' => 'datetime',
        ];
    }

    /** @return BelongsTo<SiegAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SiegAccount::class, 'sieg_account_id');
    }
}
