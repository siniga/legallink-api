<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'firm_id',
        'role_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'job_role',
        'status',
        'avatar_path',
        'is_platform_admin',
        'preferences',
        'notification_preferences',
        'last_login_at',
        'joined_at',
        'deactivated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'preferences' => 'array',
            'notification_preferences' => 'array',
            'last_login_at' => 'datetime',
            'joined_at' => 'date',
            'deactivated_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_user')->withTimestamps();
    }

    public function assignedCases(): BelongsToMany
    {
        return $this->belongsToMany(LegalCase::class, 'case_user', 'user_id', 'case_id')
            ->withPivot('is_lead')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function ownedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'owner_id');
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $first = mb_substr((string) $this->first_name, 0, 1);
            $last = mb_substr((string) $this->last_name, 0, 1);
            $initials = strtoupper($first.$last);

            if ($initials !== '') {
                return $initials;
            }

            return strtoupper(mb_substr((string) $this->name, 0, 2));
        });
    }

    public function isActive(): bool
    {
        return $this->status !== 'inactive' && $this->deactivated_at === null;
    }
}
