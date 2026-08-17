<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CaseDetailResource extends CaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $client = $this->relationLoaded('client') ? $this->client : null;

        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'claim_status' => $this->claim_status,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'assigned_lawyers' => $this->whenLoaded('assignedUsers', fn () => $this->assignedUsers->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'initials' => $user->initials,
                'is_lead' => (bool) $user->pivot->is_lead,
            ])->values()),
            'client_details' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'email' => $client->email,
                'address' => $client->address,
                'notes' => $client->notes,
            ] : null,
            'documents' => $this->whenLoaded('documents', fn () => $this->documents
                ->where('is_folder', false)
                ->values()
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'title' => $document->name,
                    'file_type' => $document->kind,
                    'uploader' => $document->relationLoaded('owner') ? $document->owner?->name : null,
                    'created_at' => optional($document->created_at)?->toIso8601String(),
                ])),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'assignee' => $task->assignee?->name,
                'due_date' => optional($task->due_at)?->toDateString(),
                'status' => $task->status,
            ])->values()),
        ]);
    }
}
