<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'title',
        'description',
        'assigned_to',
        'assigned_by',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
        'status' => TaskStatus::class,
    ];

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
