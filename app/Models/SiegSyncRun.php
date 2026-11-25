<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiegSyncRun extends Model
{
    public const COUNTERS = ['requests', 'downloaded', 'imported', 'duplicates', 'ignored', 'events', 'errors'];

    protected $table = 'sieg_sync_runs';

    protected $fillable = [
        'sieg_account_id', 'mode', 'status', 'started_at', 'finished_at',
        'requests', 'downloaded', 'imported', 'duplicates', 'ignored', 'events', 'errors',
        'from_nsu', 'to_nsu', 'message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'requests' => 'integer',
            'downloaded' => 'integer',
            'imported' => 'integer',
            'duplicates' => 'integer',
            'ignored' => 'integer',
            'events' => 'integer',
            'errors' => 'integer',
            'from_nsu' => 'integer',
            'to_nsu' => 'integer',
        ];
    }

    /** @return BelongsTo<SiegAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SiegAccount::class, 'sieg_account_id');
    }

    /** Só em memória; persiste em finish() ou save(). */
    public function bump(string $counter, int $by = 1): void
    {
        if (! in_array($counter, self::COUNTERS, true)) {
            throw new \InvalidArgumentException("Contador desconhecido: {$counter}");
        }

        $this->{$counter} = (int) $this->{$counter} + $by;
    }

    public function finish(string $status, ?string $message = null): void
    {
        $this->status = $status;
        $this->message = $message !== null ? mb_substr($message, 0, 1000) : null;
        $this->finished_at = now();
        $this->save();
    }
}
