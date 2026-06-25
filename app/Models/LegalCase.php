<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\ClaimStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalCase extends Model
{
    use HasFactory;

    protected $table = 'cases';

    protected $fillable = [
        'case_number',
        'client_id',
        'title',
        'description',
        'court_name',
        'court_date',
        'claim_status',
        'case_status',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'court_date' => 'date',
        'claim_status' => ClaimStatus::class,
        'case_status' => CaseStatus::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CaseTask::class, 'case_id');
    }
}
