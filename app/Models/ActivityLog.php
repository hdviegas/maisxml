<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'organization_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function detailLabel(): string
    {
        $p = $this->properties;
        if (! is_array($p) || $p === []) {
            return '—';
        }

        if (isset($p['chave']) && is_string($p['chave'])) {
            return $p['chave'];
        }

        if (isset($p['row_count']) && is_numeric($p['row_count'])) {
            return __('reports.detail_row_count', ['count' => (int) $p['row_count']]);
        }

        if (isset($p['ids']) && is_array($p['ids'])) {
            return __('reports.detail_bulk_zip', ['count' => count($p['ids'])]);
        }

        if (isset($p['count']) && is_numeric($p['count'])) {
            return __('reports.detail_row_count', ['count' => (int) $p['count']]);
        }

        return '—';
    }
}
