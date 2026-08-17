<?php

namespace App\Http\Resources;

use App\Models\CaseStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        $hearing = $this->relationLoaded('calendarEvents')
            ? $this->calendarEvents->first()
            : null;
        $lead = $this->relationLoaded('assignedUsers')
            ? ($this->assignedUsers->firstWhere('pivot.is_lead', true) ?: $this->assignedUsers->first())
            : null;

        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'title' => $this->title,
            'client' => $this->whenLoaded('client', fn () => $this->client?->name),
            'client_id' => $this->client_id,
            'case_type' => $this->whenLoaded('caseType', fn () => $this->caseType?->name),
            'case_type_id' => $this->case_type_id,
            'court' => $this->court,
            'status' => $this->frontendStatus($this->caseStatus),
            'status_slug' => $this->caseStatus?->slug,
            'status_label' => $this->caseStatus?->name,
            'next_hearing_at' => optional($hearing?->starts_at)?->toIso8601String(),
            'lawyer' => $lead ? [
                'id' => $lead->id,
                'name' => $lead->name,
                'initials' => $lead->initials,
            ] : null,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'mine' => $this->whenLoaded('assignedUsers', fn () => $this->assignedUsers->contains('id', $userId)),
        ];
    }

    protected function frontendStatus(?CaseStatus $status): string
    {
        return match ($status?->slug) {
            'open' => 'active',
            'hearing' => 'hearing_scheduled',
            'review' => 'pending',
            default => $status?->slug ?? 'pending',
        };
    }
}
