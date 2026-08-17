<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use BelongsToFirm, SoftDeletes;

    protected $fillable = [
        'firm_id',
        'parent_id',
        'is_folder',
        'name',
        'kind',
        'client_id',
        'case_id',
        'owner_id',
        'visibility',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_access')
            ->withPivot('access')
            ->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('visibility', 'firm')
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('visibility', 'private')->where('owner_id', $user->id);
                })
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('visibility', 'restricted')
                        ->where(function (Builder $query) use ($user) {
                            $query->where('owner_id', $user->id)
                                ->orWhereHas('accessUsers', fn ($access) => $access->where('users.id', $user->id));
                        });
                });
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->visibility === 'firm') {
            return (int) $this->firm_id === (int) $user->firm_id;
        }

        if ($this->visibility === 'private') {
            return (int) $this->owner_id === (int) $user->id;
        }

        if ((int) $this->owner_id === (int) $user->id) {
            return true;
        }

        if ($this->relationLoaded('accessUsers')) {
            return $this->accessUsers->contains('id', $user->id);
        }

        return $this->accessUsers()->where('users.id', $user->id)->exists();
    }

    public function isEditableBy(User $user): bool
    {
        if ((int) $this->owner_id === (int) $user->id) {
            return true;
        }

        if ($this->visibility !== 'restricted') {
            return false;
        }

        $isEditor = fn ($accessUser) => (int) $accessUser->id === (int) $user->id
            && in_array($accessUser->pivot->access, ['editor', 'owner'], true);

        if ($this->relationLoaded('accessUsers')) {
            return $this->accessUsers->contains($isEditor);
        }

        return $this->accessUsers()
            ->where('users.id', $user->id)
            ->whereIn('document_access.access', ['editor', 'owner'])
            ->exists();
    }

    public function hasStoredFile(): bool
    {
        return ! $this->is_folder && $this->path && Storage::disk($this->disk ?: 'local')->exists($this->path);
    }
}
