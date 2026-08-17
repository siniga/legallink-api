<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreCalendarEventRequest;
use App\Http\Requests\Calendar\UpdateCalendarEventRequest;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Auditor;
use App\Services\Notifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CalendarEvent::query()->with($this->listRelations());
        $this->applyFilters($query, $request, $user?->id);

        $events = $query->orderBy('starts_at')->limit(500)->get();

        $upcoming = CalendarEvent::query()
            ->with($this->listRelations())
            ->whereIn('type', ['hearing', 'court_mention'])
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now())
            ->when($request->boolean('mine') && $user?->id, fn ($q) => $q->where('assigned_user_id', $user->id))
            ->when($request->filled('assigned_user_id'), fn ($q) => $q->where('assigned_user_id', $request->integer('assigned_user_id')))
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => CalendarEventResource::collection($events),
            'upcoming' => CalendarEventResource::collection($upcoming),
            'stats' => $this->stats(),
            'lookups' => $this->lookups($user),
        ]);
    }

    public function show(CalendarEvent $calendarEvent): CalendarEventResource
    {
        return new CalendarEventResource($calendarEvent->load($this->listRelations()));
    }

    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        $event = $this->persist($request);

        Auditor::record(
            action: in_array($event->type, ['hearing', 'court_mention'], true) ? 'create' : 'create',
            module: 'cases',
            subject: $event,
            resourceName: $event->title,
            details: 'Calendar event created',
        );

        return response()->json([
            'message' => 'Event created.',
            'data' => new CalendarEventResource($event->load($this->listRelations())),
        ], 201);
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $oldStart = optional($calendarEvent->starts_at)?->toDayDateTimeString();
        $hearing = in_array($calendarEvent->type, ['hearing', 'court_mention'], true)
            || in_array($request->input('type'), ['hearing', 'court_mention'], true);
        $event = $this->persist($request, $calendarEvent);
        $newStart = optional($event->starts_at)?->toDayDateTimeString();
        $changedDate = $oldStart && $newStart && $oldStart !== $newStart;

        Auditor::record(
            action: $hearing && $changedDate ? 'hearing_change' : 'update',
            module: 'cases',
            subject: $event,
            resourceName: $event->title,
            details: $changedDate ? 'Old date: '.$oldStart.' · New date: '.$newStart : 'Event updated',
            oldValue: $changedDate ? $oldStart : null,
            newValue: $changedDate ? $newStart : null,
            oldLabel: $changedDate ? 'Previous Hearing' : null,
            newLabel: $changedDate ? 'New Hearing' : null,
        );

        if ($hearing && $changedDate) {
            Notifier::hearingChanged($event, $oldStart, $newStart, $request->user());
        }

        return response()->json([
            'message' => 'Event updated.',
            'data' => new CalendarEventResource($event->load($this->listRelations())),
        ]);
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        Auditor::record(
            action: 'delete',
            module: 'cases',
            subject: $calendarEvent,
            resourceName: $calendarEvent->title,
            details: 'Calendar event deleted',
        );
        $calendarEvent->delete();

        return response()->json(['message' => 'Event deleted.']);
    }

    private function persist(StoreCalendarEventRequest $request, ?CalendarEvent $event = null): CalendarEvent
    {
        $data = $request->validated();
        $user = $request->user();

        $clientId = array_key_exists('client_id', $data) ? ($data['client_id'] ?: null) : $event?->client_id;
        $caseId = array_key_exists('case_id', $data) ? ($data['case_id'] ?: null) : $event?->case_id;
        if (array_key_exists('case_id', $data) && $caseId && empty($data['client_id'])) {
            $clientId = LegalCase::query()->whereKey($caseId)->value('client_id') ?: $clientId;
        }

        $status = $data['status'] ?? $event?->status ?? 'scheduled';
        $reschedule = $request->boolean('reschedule') || $status === 'rescheduled';
        $previous = $event?->previous_starts_at;
        if ($reschedule && $event?->starts_at) {
            $previous = $event->starts_at;
            $status = 'rescheduled';
        } elseif ($status === 'adjourned' && $event?->starts_at && ! $previous) {
            $previous = $event->starts_at;
        }

        [$starts, $ends, $allDay] = $this->schedule($data, $event);

        $attributes = [
            'title' => $data['title'] ?? $event?->title,
            'type' => $data['type'] ?? $event?->type ?? 'other',
            'status' => $status,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'all_day' => $allDay,
            'case_id' => $caseId,
            'client_id' => $clientId,
            'assigned_user_id' => $data['assigned_user_id'] ?? $event?->assigned_user_id ?? $user->id,
            'location' => array_key_exists('location', $data) ? ($data['location'] ?: null) : $event?->location,
            'purpose' => array_key_exists('purpose', $data) ? ($data['purpose'] ?: null) : $event?->purpose,
            'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?: null) : $event?->notes,
            'previous_starts_at' => $previous,
            'reminder_offset' => array_key_exists('reminder', $data)
                ? $this->reminderOffset($data['reminder'] ?? null)
                : $event?->reminder_offset,
        ];

        if ($event) {
            $event->update($attributes);
        } else {
            $attributes['created_by'] = $user->id;
            $event = CalendarEvent::query()->create($attributes);
        }

        return $event->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: ?Carbon, 2: bool}
     */
    private function schedule(array $data, ?CalendarEvent $event): array
    {
        $allDay = array_key_exists('all_day', $data)
            ? (bool) $data['all_day']
            : (bool) ($event?->all_day ?? false);

        $date = $data['date'] ?? optional($event?->starts_at)?->toDateString() ?? now()->toDateString();
        $starts = Carbon::parse($date);

        if ($allDay) {
            return [$starts->startOfDay(), null, true];
        }

        $startTime = $data['start_time'] ?? optional($event?->starts_at)?->format('H:i') ?? '09:00';
        [$hours, $minutes] = array_pad(explode(':', $startTime), 2, '0');
        $starts->setTime((int) $hours, (int) $minutes);

        $ends = null;
        if (! empty($data['end_time'])) {
            $ends = Carbon::parse($date);
            [$endHours, $endMinutes] = array_pad(explode(':', $data['end_time']), 2, '0');
            $ends->setTime((int) $endHours, (int) $endMinutes);
        } elseif ($event?->ends_at && ! array_key_exists('date', $data)) {
            $ends = $event->ends_at;
        } elseif ($event?->ends_at && array_key_exists('date', $data)) {
            $duration = $event->starts_at?->diffInMinutes($event->ends_at) ?: 60;
            $ends = $starts->copy()->addMinutes($duration);
        } else {
            $ends = $starts->copy()->addHour();
        }

        return [$starts, $ends, false];
    }

    private function reminderOffset(?string $value): ?string
    {
        return match ($value) {
            null, '', 'none' => null,
            '15', '15m' => '15m',
            '30', '30m' => '30m',
            '60', '1h' => '1h',
            'day', '1d' => '1d',
            default => $value,
        };
    }

    /**
     * @return list<string>
     */
    private function listRelations(): array
    {
        return ['assignee', 'client', 'legalCase'];
    }

    private function applyFilters($query, Request $request, ?int $userId): void
    {
        $from = $request->input('from');
        $to = $request->input('to');

        if ($from) {
            $query->whereDate('starts_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('starts_at', '<=', $to);
        }

        $types = $request->input('types');
        if (is_string($types)) {
            $types = array_filter(explode(',', $types));
        }
        if (is_array($types) && $types !== []) {
            $query->whereIn('type', $types);
        }

        if ($request->boolean('mine') && $userId) {
            $query->where('assigned_user_id', $userId);
        } elseif ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->integer('assigned_user_id'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $base = CalendarEvent::query()->whereNotIn('status', ['cancelled']);
        $weekStart = now()->copy()->startOfWeek();
        $weekEnd = now()->copy()->endOfWeek();
        $today = now()->toDateString();

        $hearingsWeek = (clone $base)->whereIn('type', ['hearing', 'court_mention'])
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->count();
        $hearingsToday = (clone $base)->whereIn('type', ['hearing', 'court_mention'])
            ->whereDate('starts_at', $today)
            ->count();
        $todayCount = (clone $base)->whereDate('starts_at', $today)->count();
        $nextToday = (clone $base)->whereDate('starts_at', $today)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();
        $deadlinesWeek = (clone $base)->where('type', 'deadline')
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->count();
        $meetingsWeek = (clone $base)->where('type', 'meeting')
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->count();
        $meetingsToday = (clone $base)->where('type', 'meeting')
            ->whereDate('starts_at', $today)
            ->count();

        return [
            'hearings_week' => $hearingsWeek,
            'hearings_today' => $hearingsToday,
            'today' => $todayCount,
            'next_today' => $nextToday?->starts_at?->format('g:i A'),
            'deadlines_week' => $deadlinesWeek,
            'meetings_week' => $meetingsWeek,
            'meetings_today' => $meetingsToday,
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
