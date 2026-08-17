<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToFirm;

    public $timestamps = false;

    protected $fillable = [
        'firm_id',
        'user_id',
        'action',
        'module',
        'subject_type',
        'subject_id',
        'resource_name',
        'details',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'location',
        'session_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
