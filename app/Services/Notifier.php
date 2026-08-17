<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Document;
use App\Models\InboxNotification;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class Notifier
{
    /** @var array<string, bool> */
    private const DEFAULT_FLAGS = [
        'hearing_reminder' => true,
        'hearing_changed' => true,
        'case_status' => true,
        'case_assigned' => true,
        'task_assigned' => true,
        'task_due' => true,
        'task_overdue' => true,
        'task_done' => false,
        'doc_shared' => true,
        'doc_uploaded' => true,
        'doc_deleted' => false,
        'member_added' => false,
        'permission_changed' => true,
    ];

    public static function assignedToCase(LegalCase $case, iterable $users, ?User $actor = null): void
    {
        $case->loadMissing('client');
        foreach (self::users($users) as $user) {
            self::send(
                user: $user,
                type: 'case_assigned',
                title: 'Case assigned to you',
                body: $case->title,
                href: '/cases/'.$case->id,
                subject: $case,
                dedupeKey: 'case_assigned:'.$case->id.':'.$user->id,
                actor: $actor,
            );
        }
    }

    public static function caseStatusChanged(LegalCase $case, ?string $from, ?string $to, ?User $actor = null): void
    {
        if (! $from || ! $to || $from === $to) {
            return;
        }

        $case->loadMissing('assignedUsers');
        foreach ($case->assignedUsers as $user) {
            self::send(
                user: $user,
                type: 'case_status',
                title: 'Case status changed',
                body: $case->title.' · '.$from.' → '.$to,
                href: '/cases/'.$case->id,
                subject: $case,
                actor: $actor,
            );
        }
    }

    public static function hearingChanged(CalendarEvent $event, string $from, string $to, ?User $actor = null): void
    {
        $event->loadMissing(['assignee', 'legalCase.assignedUsers']);
        foreach (self::hearingAudience($event) as $user) {
            self::send(
                user: $user,
                type: 'hearing_changed',
                title: 'Hearing date changed',
                body: $event->title.' · '.$from.' → '.$to,
                href: $event->case_id ? '/cases/'.$event->case_id : '/calendar',
                subject: $event,
                actor: $actor,
            );
        }
    }

    public static function taskAssigned(Task $task, ?User $actor = null): void
    {
        $task->loadMissing(['assignee', 'legalCase']);
        if (! $task->assignee) {
            return;
        }

        self::send(
            user: $task->assignee,
            type: 'task_assigned',
            title: 'Task assigned to you',
            body: $task->title,
            href: '/tasks?task='.$task->id,
            subject: $task,
            actor: $actor,
        );
    }

    public static function taskCompleted(Task $task, ?User $actor = null): void
    {
        $task->loadMissing(['assignee', 'createdBy']);
        $audience = collect([$task->assignee, $task->createdBy])->filter();
        foreach ($audience as $user) {
            self::send(
                user: $user,
                type: 'task_done',
                title: 'Task completed',
                body: $task->title,
                href: '/tasks?task='.$task->id,
                subject: $task,
                actor: $actor,
            );
        }
    }

    public static function documentUploaded(Document $document, ?User $actor = null): void
    {
        $document->loadMissing(['legalCase.assignedUsers', 'client.assignedUsers']);
        $audience = collect($document->legalCase?->assignedUsers ?? [])
            ->merge($document->client?->assignedUsers ?? []);

        foreach ($audience as $user) {
            self::send(
                user: $user,
                type: 'doc_uploaded',
                title: 'Document uploaded to your case',
                body: $document->name,
                href: '/documents?id='.$document->id,
                subject: $document,
                actor: $actor,
            );
        }
    }

    public static function documentShared(Document $document, iterable $users, ?User $actor = null): void
    {
        foreach (self::users($users) as $user) {
            self::send(
                user: $user,
                type: 'doc_shared',
                title: 'Document shared with you',
                body: $document->name,
                href: $document->is_folder ? '/documents?folder='.$document->id : '/documents?id='.$document->id,
                subject: $document,
                actor: $actor,
            );
        }
    }

    public static function documentDeleted(Document $document, ?User $actor = null): void
    {
        $document->loadMissing(['legalCase.assignedUsers', 'accessUsers']);
        $audience = collect($document->legalCase?->assignedUsers ?? [])
            ->merge($document->accessUsers ?? []);

        foreach ($audience as $user) {
            self::send(
                user: $user,
                type: 'doc_deleted',
                title: 'Document deleted',
                body: $document->name,
                href: '/documents',
                subject: $document,
                actor: $actor,
            );
        }
    }

    public static function memberAdded(User $member, ?User $actor = null): void
    {
        if (! $member->firm_id) {
            return;
        }

        $others = User::query()
            ->where('firm_id', $member->firm_id)
            ->where('id', '!=', $member->id)
            ->where('status', '!=', 'inactive')
            ->whereNull('deactivated_at')
            ->get();

        foreach ($others as $user) {
            self::send(
                user: $user,
                type: 'member_added',
                title: 'New team member added',
                body: $member->name,
                href: '/team',
                subject: $member,
                actor: $actor,
            );
        }
    }

    public static function permissionChanged(User $member, string $details, ?User $actor = null): void
    {
        self::send(
            user: $member,
            type: 'permission_changed',
            title: 'Your access was updated',
            body: $details,
            href: '/settings',
            subject: $member,
            actor: $actor,
        );
    }

    public static function dispatchDueReminders(): void
    {
        self::hearingReminders();
        self::taskReminders();
    }

    public static function send(
        User $user,
        string $type,
        string $title,
        ?string $body,
        ?string $href,
        ?Model $subject = null,
        ?string $dedupeKey = null,
        ?User $actor = null,
    ): void {
        try {
            $actor = $actor ?? request()?->user();
            if ((int) $user->id === (int) $actor?->id) {
                return;
            }
            if (! $user->isActive() || ! $user->firm_id) {
                return;
            }
            if (! self::wants($user, $type)) {
                return;
            }

            $payload = [
                'firm_id' => $user->firm_id,
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'href' => $href,
                'subject_type' => match (true) {
                    $subject instanceof LegalCase => 'cases',
                    $subject instanceof Task => 'tasks',
                    $subject instanceof Document => 'documents',
                    $subject instanceof CalendarEvent => 'calendar_events',
                    $subject instanceof User => 'users',
                    $subject instanceof Model => $subject->getTable(),
                    default => null,
                },
                'subject_id' => $subject?->getKey(),
                'dedupe_key' => $dedupeKey,
            ];

            if ($dedupeKey) {
                InboxNotification::query()->withoutGlobalScopes()->updateOrCreate(
                    ['user_id' => $user->id, 'dedupe_key' => $dedupeKey],
                    $payload,
                );

                return;
            }

            InboxNotification::query()->create($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function hearingReminders(): void
    {
        $events = CalendarEvent::query()
            ->withoutGlobalScopes()
            ->with(['assignee', 'legalCase.assignedUsers'])
            ->when(auth()->user()?->firm_id, fn ($query, $firmId) => $query->where('firm_id', $firmId))
            ->whereIn('type', ['hearing', 'court_mention'])
            ->whereNotIn('status', ['cancelled', 'adjourned'])
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addWeek())
            ->get();

        foreach ($events as $event) {
            foreach (self::hearingAudience($event) as $user) {
                $when = self::reminderAt($event->starts_at, self::offset($user, 'hearing'), $event->reminder_offset);
                if (! $when || $when->isFuture()) {
                    continue;
                }

                $date = optional($event->starts_at)?->format('D j M, g:i A');
                self::send(
                    user: $user,
                    type: 'hearing_reminder',
                    title: 'Upcoming hearing',
                    body: $event->title.($date ? ' · '.$date : ''),
                    href: $event->case_id ? '/cases/'.$event->case_id : '/calendar',
                    subject: $event,
                    dedupeKey: 'hearing_reminder:'.$event->id.':'.optional($event->starts_at)?->toDateString(),
                    actor: null,
                );
            }
        }
    }

    private static function taskReminders(): void
    {
        $tasks = Task::query()
            ->withoutGlobalScopes()
            ->with('assignee')
            ->when(auth()->user()?->firm_id, fn ($query, $firmId) => $query->where('firm_id', $firmId))
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('due_at', '<=', now()->addWeek())
            ->get();

        foreach ($tasks as $task) {
            $user = $task->assignee;
            if (! $user) {
                continue;
            }

            $due = $task->due_at;
            if ($due->lt(now())) {
                self::send(
                    user: $user,
                    type: 'task_overdue',
                    title: 'Task overdue',
                    body: $task->title,
                    href: '/tasks?task='.$task->id,
                    subject: $task,
                    dedupeKey: 'task_overdue:'.$task->id.':'.$due->toDateString(),
                    actor: null,
                );

                continue;
            }

            $when = self::reminderAt($due, self::offset($user, 'task'), $task->reminder_offset);
            if (! $when || $when->isFuture()) {
                continue;
            }

            self::send(
                user: $user,
                type: 'task_due',
                title: 'Task due soon',
                body: $task->title,
                href: '/tasks?task='.$task->id,
                subject: $task,
                dedupeKey: 'task_due:'.$task->id.':'.$due->toDateString(),
                actor: null,
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private static function hearingAudience(CalendarEvent $event): Collection
    {
        return collect([$event->assignee])
            ->merge($event->legalCase?->assignedUsers ?? [])
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private static function users(iterable $users): Collection
    {
        return collect($users)->filter(fn ($user) => $user instanceof User)->unique('id')->values();
    }

    public static function wants(User $user, string $type): bool
    {
        $flags = self::flags($user);

        return (bool) ($flags[$type] ?? self::DEFAULT_FLAGS[$type] ?? true);
    }

    /**
     * @return array<string, bool>
     */
    private static function flags(User $user): array
    {
        $prefs = $user->notification_preferences ?? [];
        if (isset($prefs['flags']) && is_array($prefs['flags'])) {
            return $prefs['flags'];
        }

        return array_filter($prefs, fn ($value) => is_bool($value));
    }

    private static function offset(User $user, string $kind): string
    {
        $prefs = $user->notification_preferences ?? [];
        if ($kind === 'hearing') {
            $value = $prefs['hearing_reminder'] ?? $prefs['hearing_reminder_offset'] ?? '1d';
        } else {
            $value = $prefs['task_reminder'] ?? $prefs['task_reminder_offset'] ?? '1d';
        }

        return is_string($value) ? $value : '1d';
    }

    private static function reminderAt(?Carbon $at, string $userOffset, ?string $itemOffset): ?Carbon
    {
        if (! $at) {
            return null;
        }

        $offset = $itemOffset ?: $userOffset;

        return $at->copy()->sub(self::interval($offset));
    }

    private static function interval(string $offset): \DateInterval
    {
        return match ($offset) {
            '30m' => new \DateInterval('PT30M'),
            '1h' => new \DateInterval('PT1H'),
            '3h' => new \DateInterval('PT3H'),
            '2d' => new \DateInterval('P2D'),
            '1w' => new \DateInterval('P7D'),
            default => new \DateInterval('P1D'),
        };
    }
}
