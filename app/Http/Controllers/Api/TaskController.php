<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Services\Auditor;
use App\Services\Notifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 100);

        $query = Task::query()->with($this->listRelations());
        $this->applyFilters($query, $request, $user?->id);
        $this->applySort($query, $request);

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => TaskResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'stats' => $this->stats($user?->id),
            'lookups' => $this->lookups($user),
        ]);
    }

    public function show(Task $task): TaskResource
    {
        return new TaskResource($task->load($this->listRelations()));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->persist($request)->load($this->listRelations());

        Auditor::record(
            action: 'create',
            module: 'tasks',
            subject: $task,
            resourceName: $task->title,
            details: $task->assignee ? 'Assigned to '.$task->assignee->name : 'New task created',
        );
        Notifier::taskAssigned($task, $request->user());

        return response()->json([
            'message' => 'Task created.',
            'data' => new TaskResource($task),
        ], 201);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $oldStatus = $task->status;
        $oldAssignee = $task->assignee_id;
        $task = $this->persist($request, $task)->load($this->listRelations());

        $details = 'Task updated';
        if ($oldStatus !== $task->status) {
            $details = 'Status changed to '.str_replace('_', ' ', $task->status);
        } elseif ($oldAssignee !== $task->assignee_id) {
            $details = $task->assignee ? 'Reassigned to '.$task->assignee->name : 'Assignee updated';
        }

        Auditor::record(
            action: 'update',
            module: 'tasks',
            subject: $task,
            resourceName: $task->title,
            details: $details,
            oldValue: $oldStatus !== $task->status ? $oldStatus : null,
            newValue: $oldStatus !== $task->status ? $task->status : null,
        );

        if ($oldAssignee !== $task->assignee_id) {
            Notifier::taskAssigned($task, $request->user());
        }
        if ($oldStatus !== 'completed' && $task->status === 'completed') {
            Notifier::taskCompleted($task, $request->user());
        }

        return response()->json([
            'message' => 'Task updated.',
            'data' => new TaskResource($task),
        ]);
    }

    public function destroy(Task $task): JsonResponse
    {
        Auditor::record(
            action: 'delete',
            module: 'tasks',
            subject: $task,
            resourceName: $task->title,
            details: 'Task deleted',
        );
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    public function toggleChecklist(Task $task, TaskChecklistItem $checklistItem): JsonResponse
    {
        if ((int) $checklistItem->task_id !== (int) $task->id) {
            abort(404);
        }

        $checklistItem->update(['is_done' => ! $checklistItem->is_done]);

        return response()->json([
            'message' => 'Checklist updated.',
            'data' => new TaskResource($task->fresh()->load($this->listRelations())),
        ]);
    }

    private function persist(StoreTaskRequest $request, ?Task $task = null): Task
    {
        $data = $request->validated();
        $user = $request->user();

        $clientId = array_key_exists('client_id', $data) ? ($data['client_id'] ?: null) : $task?->client_id;
        $caseId = array_key_exists('case_id', $data) ? ($data['case_id'] ?: null) : $task?->case_id;
        if (array_key_exists('case_id', $data) && $caseId && empty($data['client_id'])) {
            $clientId = LegalCase::query()->whereKey($caseId)->value('client_id') ?: $clientId;
        }

        $status = $data['status'] ?? $task?->status ?? 'pending';
        $completedAt = $task?->completed_at;
        if ($status === 'completed' && $task?->status !== 'completed') {
            $completedAt = now();
        } elseif ($status !== 'completed') {
            $completedAt = null;
        }

        $attributes = [
            'title' => $data['title'] ?? $task?->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $task?->description,
            'case_id' => $caseId,
            'client_id' => $clientId,
            'assignee_id' => $data['assignee_id'] ?? $task?->assignee_id ?? $user->id,
            'due_at' => $this->dueAt($data, $task),
            'priority' => $data['priority'] ?? $task?->priority ?? 'medium',
            'status' => $status,
            'reminder_offset' => array_key_exists('reminder_offset', $data)
                ? ($data['reminder_offset'] ?: null)
                : $task?->reminder_offset,
            'completed_at' => $completedAt,
        ];

        if ($task) {
            $task->update($attributes);
        } else {
            $attributes['created_by'] = $user->id;
            $task = Task::query()->create($attributes);
        }

        return $task->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dueAt(array $data, ?Task $task): ?Carbon
    {
        if (! array_key_exists('due_date', $data) && ! array_key_exists('due_time', $data)) {
            return $task?->due_at;
        }

        $date = array_key_exists('due_date', $data)
            ? ($data['due_date'] ?: null)
            : optional($task?->due_at)?->toDateString();
        if (! $date) {
            return null;
        }

        $parsed = Carbon::parse($date);
        $time = $data['due_time'] ?? null;
        if ($time) {
            [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');
            $parsed->setTime((int) $hours, (int) $minutes);
        } elseif ($task?->due_at) {
            $parsed->setTime((int) $task->due_at->format('H'), (int) $task->due_at->format('i'));
        } else {
            $parsed->setTime(17, 0);
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function listRelations(): array
    {
        return ['assignee', 'createdBy', 'client', 'legalCase', 'checklistItems'];
    }

    private function applyFilters($query, Request $request, ?int $userId): void
    {
        $tab = $request->string('tab')->toString();
        $search = trim((string) $request->input('search', ''));
        $today = now()->toDateString();

        if ($tab === 'mine' && $userId) {
            $query->where('assignee_id', $userId);
        } elseif ($tab === 'today') {
            $query->whereDate('due_at', $today)->whereNotIn('status', ['completed', 'cancelled']);
        } elseif ($tab === 'upcoming') {
            $query->whereDate('due_at', '>', $today)->whereNotIn('status', ['completed', 'cancelled']);
        } elseif ($tab === 'overdue') {
            $query->whereNotNull('due_at')
                ->whereDate('due_at', '<', $today)
                ->whereNotIn('status', ['completed', 'cancelled']);
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('legalCase', function ($case) use ($search) {
                        $case->where('title', 'like', "%{$search}%")
                            ->orWhere('case_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->integer('assignee_id'));
        }
        if ($request->filled('case_id')) {
            $query->where('case_id', $request->integer('case_id'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->integer('created_by'));
        }
        if ($request->filled('due_from')) {
            $query->whereDate('due_at', '>=', $request->input('due_from'));
        }
        if ($request->filled('due_to')) {
            $query->whereDate('due_at', '<=', $request->input('due_to'));
        }
    }

    private function applySort($query, Request $request): void
    {
        $direction = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $sort = $request->string('sort')->toString();

        match ($sort) {
            'title' => $query->orderBy('title', $direction),
            'due' => $query->orderBy('due_at', $direction),
            'priority' => $query->orderByRaw("FIELD(priority, 'high', 'medium', 'low') ".$direction),
            'status' => $query->orderBy('status', $direction),
            default => $query->latest('updated_at'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(?int $userId): array
    {
        $base = Task::query();
        $open = fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled']);
        $today = now()->toDateString();
        $weekStart = now()->copy()->startOfWeek();
        $weekEnd = now()->copy()->endOfWeek();

        $mineQuery = (clone $base)->when($userId, fn ($query) => $query->where('assignee_id', $userId));
        $mine = $open(clone $mineQuery)->count();
        $mineDueWeek = $open(clone $mineQuery)->whereBetween('due_at', [$weekStart, $weekEnd])->count();

        $dueToday = $open(clone $base)->whereDate('due_at', $today)->count();
        $todayHigh = $open(clone $base)->whereDate('due_at', $today)->where('priority', 'high')->count();
        $overdue = $open(clone $base)->whereNotNull('due_at')->whereDate('due_at', '<', $today)->count();
        $doneWeek = (clone $base)->where('status', 'completed')
            ->where(function ($query) use ($weekStart) {
                $query->where('completed_at', '>=', $weekStart)
                    ->orWhere(function ($query) use ($weekStart) {
                        $query->whereNull('completed_at')->where('updated_at', '>=', $weekStart);
                    });
            })
            ->count();

        return [
            'mine' => $mine,
            'mine_due_week' => $mineDueWeek,
            'today' => $dueToday,
            'today_high' => $todayHigh,
            'overdue' => $overdue,
            'completed_week' => $doneWeek,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(?User $user): array
    {
        $firmId = $user?->firm_id;

        return [
            'users' => User::query()
                ->when($firmId, fn ($query) => $query->where('firm_id', $firmId))
                ->where('status', '!=', 'inactive')
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'initials' => $member->initials,
                ])
                ->values()
                ->all(),
            'cases' => LegalCase::query()
                ->with('client')
                ->orderBy('title')
                ->get(['id', 'title', 'case_number', 'client_id'])
                ->map(fn (LegalCase $case) => [
                    'id' => $case->id,
                    'title' => $case->title,
                    'number' => $case->case_number,
                    'client' => $case->client?->name,
                    'client_id' => $case->client_id,
                ])
                ->values()
                ->all(),
            'clients' => Client::query()
                ->where('status', '!=', 'archived')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
