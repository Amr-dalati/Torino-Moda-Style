<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoenixSyncLog extends Model
{
    protected $fillable = [
        'sync_type',
        'entity_type',
        'entity_id',
        'direction',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'started_at',
        'finished_at',
        'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
