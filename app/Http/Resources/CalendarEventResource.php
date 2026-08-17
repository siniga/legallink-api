<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $starts = $this->starts_at;
        $ends = $this->ends_at;
        $allDay = (bool) $this->all_day;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'date' => optional($starts)?->toDateString(),
            'start_time' => $allDay || ! $starts ? null : $starts->format('g:i A'),
            'end_time' => $allDay || ! $ends ? null : $ends->format('g:i A'),
            'start_time_input' => $allDay || ! $starts ? null : $starts->format('H:i'),
            'end_time_input' => $allDay || ! $ends ? null : $ends->format('H:i'),
            'all_day' => $allDay,
            'starts_at' => optional($starts)?->toIso8601String(),
            'ends_at' => optional($ends)?->toIso8601String(),
            'case_id' => $this->case_id,
            'case_number' => $this->whenLoaded('legalCase', fn () => $this->legalCase?->case_number),
            'case_title' => $this->whenLoaded('legalCase', fn () => $this->legalCase?->title),
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'court' => $this->location,
            'assigned_user_id' => $this->assigned_user_id,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'initials' => $this->assignee->initials,
            ] : null),
            'purpose' => $this->purpose,
            'notes' => $this->notes,
            'previous_date' => optional($this->previous_starts_at)?->toDateString(),
            'reminder' => $this->reminder_offset,
            'mine' => (int) $this->assigned_user_id === (int) $request->user()?->id,
        ];
    }
}
