<?php

namespace App\Http\Resources;

use App\Models\CaseStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeCases = (int) ($this->active_cases_count ?? 0);
        $openTasks = (int) ($this->open_tasks_count ?? 0);

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_role' => $this->job_role,
            'access_role' => $this->role?->slug,
            'access_role_title' => $this->role?->title,
            'status' => $this->status,
            'workload' => $this->workloadLevel($activeCases, $openTasks),
            'active_cases' => $activeCases,
            'open_tasks' => $openTasks,
            'completed_tasks' => (int) ($this->completed_tasks_count ?? 0),
            'documents_shared' => (int) ($this->documents_shared_count ?? 0),
            'last_login_at' => optional($this->last_login_at)?->toIso8601String(),
            'joined_at' => optional($this->joined_at)?->toDateString(),
            'cases' => $this->whenLoaded('assignedCases', fn () => $this->assignedCases->map(fn ($case) => [
                'id' => $case->id,
                'title' => $case->title,
                'number' => $case->case_number,
                'status' => $this->frontendCaseStatus($case->caseStatus),
                'lead' => (bool) $case->pivot->is_lead,
            ])->values()),
            'tasks' => $this->whenLoaded('assignedTasks', fn () => $this->assignedTasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'due_at' => optional($task->due_at)?->toIso8601String(),
                'due_date' => optional($task->due_at)?->toDateString(),
                'priority' => $task->priority,
                'status' => $task->status,
            ])->values()),
            'activity' => $this->when($this->relationLoaded('assignedTasks'), fn () => $this->activity()),
            'permissions' => $this->when(
                $this->relationLoaded('role') && $this->role?->relationLoaded('permissions'),
                fn () => $this->role->permissions->pluck('slug')->values(),
            ),
        ];
    }

    /**
     * @return list<array{id: string, text: string, at: string|null}>
     */
    private function activity(): array
    {
        $items = [];

        if ($this->last_login_at) {
            $items[] = [
                'id' => 'login-'.$this->id,
                'text' => $this->name.' signed in',
                'at' => optional($this->last_login_at)?->toIso8601String(),
            ];
        }

        if ($this->relationLoaded('assignedTasks')) {
            foreach ($this->assignedTasks->where('status', 'completed')->take(3) as $task) {
                $items[] = [
                    'id' => 'task-'.$task->id,
                    'text' => 'Completed task: '.$task->title,
                    'at' => optional($task->completed_at ?? $task->updated_at)?->toIso8601String(),
                ];
            }
        }

        return $items;
    }

    private function workloadLevel(int $cases, int $tasks): string
    {
        if ($cases >= 10 || $tasks >= 6) {
            return 'high';
        }
        if ($cases >= 5 || $tasks >= 3) {
            return 'medium';
        }

        return 'low';
    }

    private function frontendCaseStatus(?CaseStatus $status): string
    {
        return match ($status?->slug) {
            'open' => 'active',
            'hearing' => 'hearing_scheduled',
            'review' => 'pending',
            default => $status?->slug ?? 'pending',
        };
    }
}
