<?php

namespace App\Models;

use App\Services\Sieg\SiegConnection;
use Database\Factories\SiegAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta SIEG de uma empresa. `api_key` é criptografada e nunca serializada.
 */
class SiegAccount extends Model
{
    /** @use HasFactory<SiegAccountFactory> */
    use HasFactory;

    protected $table = 'sieg_accounts';

    protected $fillable = [
        'organization_id', 'name', 'api_key', 'mode', 'last_nsu', 'enabled', 'paused_until',
        'consecutive_failures', 'last_run_at', 'last_success_at', 'last_error',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'paused_until' => 'datetime',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_nsu' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    /** @return HasMany<SiegSyncCursor, $this> */
    public function cursors(): HasMany
    {
        return $this->hasMany(SiegSyncCursor::class, 'sieg_account_id');
    }

    /** @return HasMany<SiegSyncRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(SiegSyncRun::class, 'sieg_account_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function connection(): SiegConnection
    {
        return new SiegConnection((string) $this->api_key);
    }

    public function isPaused(): bool
    {
        return $this->paused_until !== null && $this->paused_until->isFuture();
    }

    /**
     * @param  Builder<SiegAccount>  $query
     * @return Builder<SiegAccount>
     */
    public function scopeRunnable(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->where(fn (Builder $q) => $q->whereNull('paused_until')->orWhere('paused_until', '<=', now()));
    }

    public function maskedKey(): string
    {
        $key = (string) $this->api_key;

        return '••••'.($key === '' ? '' : substr($key, -4));
    }

    /** O chamador não deve passar mensagens com segredos. */
    public function recordFailure(string $message): void
    {
        $this->consecutive_failures = (int) $this->consecutive_failures + 1;
        $this->last_error = mb_substr($message, 0, 1000);

        if ($this->consecutive_failures >= (int) config('sieg.sync.pause_after_failures')) {
            $this->paused_until = now()->addMinutes((int) config('sieg.sync.pause_minutes'));
        }

        $this->save();
    }

    public function recordSuccess(): void
    {
        $this->consecutive_failures = 0;
        $this->last_error = null;
        $this->last_success_at = now();
        $this->save();
    }

    public function __debugInfo(): array
    {
        return array_merge($this->attributes, ['api_key' => '***']);
    }

    public function __toString(): string
    {
        return 'SiegAccount#'.$this->getKey().' ('.$this->name.')';
    }
}
