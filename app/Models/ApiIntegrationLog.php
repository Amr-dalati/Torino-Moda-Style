<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIntegrationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'correlation_id',
        'method',
        'url',
        'status_code',
        'request_body',
        'response_body',
        'duration_ms',
        'is_mock',
    ];

    protected function casts(): array
    {
        return [
            'request_body' => 'array',
            'response_body' => 'array',
            'is_mock' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
