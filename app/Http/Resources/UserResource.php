<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_role' => $this->job_role,
            'status' => $this->status,
            'initials' => $this->initials,
            'is_platform_admin' => $this->is_platform_admin,
            'firm' => $this->whenLoaded('firm', fn () => $this->firm ? [
                'id' => $this->firm->id,
                'name' => $this->firm->name,
                'slug' => $this->firm->slug,
            ] : null),
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id,
                'slug' => $this->role->slug,
                'title' => $this->role->title,
            ] : null),
            'preferences' => $this->preferences ?? [],
        ];
    }
}
