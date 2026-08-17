<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Firm extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'country',
        'registration_number',
        'logo_path',
        'case_number_format',
        'document_settings',
        'audit_retention',
        'status',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'document_settings' => 'array',
            'deactivated_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class)->withoutGlobalScope('firm');
    }

    public function legalCases(): HasMany
    {
        return $this->hasMany(LegalCase::class)->withoutGlobalScope('firm');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->withoutGlobalScope('firm');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deactivated_at === null;
    }
}
