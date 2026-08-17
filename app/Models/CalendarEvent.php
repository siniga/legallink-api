<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use BelongsToFirm;

    protected $fillable = [
        'firm_id',
        'title',
        'type',
        'status',
        'starts_at',
        'ends_at',
        'all_day',
        'case_id',
        'client_id',
        'assigned_user_id',
        'location',
        'purpose',
        'notes',
        'previous_starts_at',
        'created_by',
        'reminder_offset',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'previous_starts_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
