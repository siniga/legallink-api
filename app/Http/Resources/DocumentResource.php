<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $owner = $this->relationLoaded('owner') ? $this->owner : null;
        $related = $this->relationLoaded('legalCase') && $this->legalCase
            ? $this->legalCase->title
            : ($this->relationLoaded('client') && $this->client ? $this->client->name : ($this->is_folder ? $this->name : 'Documents'));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'is_folder' => $this->is_folder,
            'parent_id' => $this->parent_id,
            'related_to' => $related,
            'client_id' => $this->client_id,
            'case_id' => $this->case_id,
            'owner' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'initials' => $owner->initials,
                'role' => $owner->job_role ?: 'Team member',
            ] : null,
            'visibility' => $this->visibility,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'size_bytes' => (int) ($this->size_bytes ?? 0),
            'has_file' => $this->hasStoredFile(),
            'can_edit' => $user ? $this->isEditableBy($user) : false,
            'allowed_user_ids' => $this->whenLoaded('accessUsers', fn () => $this->accessUsers->pluck('id')->values()),
            'access' => $this->whenLoaded('accessUsers', fn () => $this->accessUsers->map(fn ($accessUser) => [
                'id' => $accessUser->id,
                'access' => $accessUser->pivot->access,
            ])->values()),
        ];
    }
}
