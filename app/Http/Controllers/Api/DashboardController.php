<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\CaseStatus;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'stats' => $this->stats($user),
            'hearings' => $this->hearings(),
            'activity' => $this->activity(),
            'tasks' => $this->tasks($user),
            'cases' => $this->cases($user),
            'documents' => $this->documents($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(User $user): array
    {
        $weekStart = now()->copy()->startOfWeek();
        $weekEnd = now()->copy()->endOfWeek();
        $monthStart = now()->copy()->startOfMonth();
        $today = now()->toDateString();

        $activeCases = LegalCase::query()
            ->whereHas('caseStatus', fn ($status) => $status->where('is_closed', false)->where('is_archived', false))
            ->count();
        $newCasesMonth = LegalCase::query()->where('created_at', '>=', $monthStart)->count();

        $hearingsQuery = CalendarEvent::query()
            ->whereIn('type', ['hearing', 'court_mention'])
            ->whereNotIn('status', ['cancelled']);
        $hearingsWeek = (clone $hearingsQuery)
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->count();
        $nextHearing = (clone $hearingsQuery)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();

        $documentsWeek = Document::query()
            ->visibleTo($user)
            ->where('is_folder', false)
            ->where('created_at', '>=', $weekStart)
            ->count();

        $overdueTasks = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', $today)
            ->count();

        return [
            'active_cases' => $activeCases,
            'new_cases_month' => $newCasesMonth,
            'hearings_week' => $hearingsWeek,
            'next_hearing_at' => optional($nextHearing?->starts_at)?->toIso8601String(),
            'documents_week' => $documentsWeek,
            'overdue_tasks' => $overdueTasks,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hearings(): array
    {
        return CalendarEvent::query()
            ->with('legalCase')
            ->whereIn('type', ['hearing', 'court_mention'])
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(function (CalendarEvent $event) {
                $case = $event->legalCase;

                return [
                    'id' => $event->id,
                    'case_id' => $event->case_id,
                    'title' => $case?->title ?: $event->title,
                    'court' => $event->location ?: ($case?->court ?: '—'),
                    'starts_at' => optional($event->starts_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activity(): array
    {
        return AuditLog::query()
            ->with('user')
            ->whereNotIn('module', ['security'])
            ->whereNotIn('action', ['login', 'failed_login', 'new_device_login', 'password_change', 'session_revoked'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'type' => $this->activityType($log),
                'actor' => $log->user?->name ?: 'Someone',
                'action' => $this->activityAction($log),
                'subject' => $this->activitySubject($log),
                'occurred_at' => optional($log->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tasks(User $user): array
    {
        return Task::query()
            ->with(['legalCase', 'client'])
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'related' => $task->legalCase?->title ?: ($task->client?->name ?: '—'),
                'due_at' => optional($task->due_at)?->toIso8601String(),
                'priority' => $task->priority ?: 'medium',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cases(User $user): array
    {
        return LegalCase::query()
            ->with([
                'client',
                'caseStatus',
                'calendarEvents' => function ($query) {
                    $query->where('status', 'scheduled')
                        ->whereIn('type', ['hearing', 'court_mention'])
                        ->where('starts_at', '>=', now())
                        ->orderBy('starts_at');
                },
            ])
            ->whereHas('assignedUsers', fn ($assigned) => $assigned->where('users.id', $user->id))
            ->whereHas('caseStatus', fn ($status) => $status->where('is_closed', false)->where('is_archived', false))
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function (LegalCase $case) {
                $hearing = $case->calendarEvents->first();

                return [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'title' => $case->title,
                    'client' => $case->client?->name ?: '—',
                    'next_hearing_at' => optional($hearing?->starts_at)?->toIso8601String(),
                    'status' => $this->frontendStatus($case->caseStatus),
                    'status_label' => $case->caseStatus?->name ?: 'Pending',
                    'priority' => $this->casePriority($hearing?->starts_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documents(User $user): array
    {
        return Document::query()
            ->with(['client', 'legalCase'])
            ->visibleTo($user)
            ->where('is_folder', false)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->name,
                'related' => $document->legalCase?->title ?: ($document->client?->name ?: 'Documents'),
                'updated_at' => optional($document->updated_at)?->toIso8601String(),
                'kind' => $document->kind ?: 'other',
            ])
            ->values()
            ->all();
    }

    private function frontendStatus(?CaseStatus $status): string
    {
        return match ($status?->slug) {
            'open' => 'active',
            'hearing' => 'hearing_scheduled',
            'review' => 'pending',
            default => $status?->slug ?? 'pending',
        };
    }

    private function casePriority(?Carbon $hearingAt): string
    {
        if (! $hearingAt) {
            return 'Low';
        }
        if ($hearingAt->lte(now()->addDays(3))) {
            return 'High';
        }
        if ($hearingAt->lte(now()->addDays(7))) {
            return 'Medium';
        }

        return 'Low';
    }

    private function activityType(AuditLog $log): string
    {
        return match ($log->action) {
            'upload', 'download' => 'upload',
            'share' => 'share',
            'hearing_change' => 'hearing',
            default => match ($log->module) {
                'clients' => 'client',
                'documents' => 'upload',
                default => str_contains((string) $log->details, 'hearing') || str_contains((string) $log->details, 'Calendar')
                    ? 'hearing'
                    : 'update',
            },
        };
    }

    private function activityAction(AuditLog $log): string
    {
        $name = $log->resource_name ?: 'a record';

        return match ($log->action) {
            'upload' => 'uploaded '.$name,
            'download' => 'downloaded '.$name,
            'share' => 'shared '.$name,
            'create' => match ($log->module) {
                'clients' => 'added a new client',
                'cases' => str_contains((string) $log->details, 'Calendar') ? 'added a calendar event' : 'created a case',
                'tasks' => 'created a task',
                'documents' => 'added '.$name,
                'team' => 'added a team member',
                default => 'created '.$name,
            },
            'hearing_change' => 'changed hearing date',
            'status_change' => 'changed case status',
            'delete' => 'deleted '.$name,
            'update' => $log->details ? lcfirst((string) $log->details) : 'updated '.$name,
            default => $log->details ? lcfirst((string) $log->details) : str_replace('_', ' ', (string) $log->action),
        };
    }

    private function activitySubject(AuditLog $log): string
    {
        if (in_array($log->action, ['upload', 'download', 'share'], true) && $log->details) {
            return $log->details;
        }

        return $log->resource_name ?: '—';
    }
}
