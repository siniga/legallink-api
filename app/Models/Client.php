<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToFirm, SoftDeletes;

    protected $fillable = [
        'firm_id',
        'type',
        'status',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'id_number',
        'occupation',
        'registration_number',
        'industry',
        'tin',
        'notes',
        'created_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_user')->withTimestamps();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    public function clientNotes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest();
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
            $initials = collect($words)
                ->filter()
                ->take(2)
                ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
                ->implode('');

            return $initials !== '' ? $initials : 'CL';
        });
    }

    public function archive(): void
    {
        $this->forceFill([
            'status' => 'archived',
            'archived_at' => now(),
        ])->save();
    }
}
