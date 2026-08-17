<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $due = $this->due_at;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'case_id' => $this->case_id,
            'case_title' => $this->whenLoaded('legalCase', fn () => $this->legalCase?->title),
            'case_number' => $this->whenLoaded('legalCase', fn () => $this->legalCase?->case_number),
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'assignee_id' => $this->assignee_id,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'initials' => $this->assignee->initials,
            ] : null),
            'created_by_id' => $this->created_by,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'initials' => $this->createdBy->initials,
            ] : null),
            'due_at' => optional($due)?->toIso8601String(),
            'due_date' => optional($due)?->toDateString(),
            'due_time' => $due && $due->format('H:i:s') !== '00:00:00' && $due->format('H:i:s') !== '23:59:59'
                ? $due->format('g:i A')
                : null,
            'priority' => $this->priority,
            'status' => $this->status,
            'reminder_offset' => $this->reminder_offset,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
            'mine' => (int) $this->assignee_id === (int) $request->user()?->id,
            'checklist' => $this->whenLoaded('checklistItems', fn () => $this->checklistItems->map(fn ($item) => [
                'id' => $item->id,
                'text' => $item->text,
                'done' => (bool) $item->is_done,
            ])->values()),
            'activity' => $this->activity(),
        ];
    }

    /**
     * @return list<array{id: string, task_id: int, actor: string, text: string, at: string|null}>
     */
    private function activity(): array
    {
        $items = [];

        if ($this->relationLoaded('createdBy') && $this->createdBy) {
            $items[] = [
                'id' => 'created-'.$this->id,
                'task_id' => $this->id,
                'actor' => $this->createdBy->name,
                'text' => 'created this task',
                'at' => optional($this->created_at)?->toIso8601String(),
            ];
        }

        if ($this->status === 'completed' && $this->completed_at) {
            $items[] = [
                'id' => 'completed-'.$this->id,
                'task_id' => $this->id,
                'actor' => $this->assignee?->name ?? 'Someone',
                'text' => 'completed this task',
                'at' => optional($this->completed_at)?->toIso8601String(),
            ];
        }

        return $items;
    }
}
