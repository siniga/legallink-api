<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        $primary = $this->relationLoaded('primaryContact') ? $this->primaryContact : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials,
            'type' => $this->type,
            'status' => $this->status,
            'primary_contact' => $primary?->name ?: $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'registration_number' => $this->registration_number,
            'industry' => $this->industry,
            'occupation' => $this->occupation,
            'id_number' => $this->id_number,
            'tin' => $this->tin,
            'open_cases' => (int) ($this->open_cases_count ?? 0),
            'closed_cases' => (int) ($this->closed_cases_count ?? 0),
            'documents_count' => (int) ($this->documents_count ?? 0),
            'assigned_lawyers' => $this->whenLoaded('assignedUsers', fn () => $this->assignedUsers->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'initials' => $user->initials,
            ])->values()),
            'last_activity_at' => optional($this->updated_at)?->toIso8601String(),
            'mine' => $this->whenLoaded('assignedUsers', fn () => $this->assignedUsers->contains('id', $userId)),
        ];
    }
}
