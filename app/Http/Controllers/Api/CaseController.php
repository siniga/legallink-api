<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LegalCase\StoreCaseRequest;
use App\Http\Requests\LegalCase\UpdateCaseRequest;
use App\Http\Resources\CaseDetailResource;
use App\Http\Resources\CaseResource;
use App\Models\CalendarEvent;
use App\Models\CaseStatus;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\LegalCase;
use App\Services\Auditor;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);

        $query = LegalCase::query()->with($this->listRelations());

        $this->applyFilters($query, $request, $user?->id);
        $this->applySort($query, $request);

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => CaseResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'stats' => $this->stats(),
            'lookups' => $this->lookups(),
        ]);
    }

    public function show(LegalCase $legalCase): CaseDetailResource
    {
        return new CaseDetailResource($this->loadDetail($legalCase));
    }

    public function store(StoreCaseRequest $request): JsonResponse
    {
        $legalCase = $this->persist($request)->load('assignedUsers');

        Auditor::record(
            action: 'create',
            module: 'cases',
            subject: $legalCase,
            resourceName: $legalCase->title,
            details: 'New case created',
        );
        Notifier::assignedToCase($legalCase, $legalCase->assignedUsers, $request->user());

        return response()->json([
            'message' => 'Case created.',
            'data' => new CaseResource($legalCase->load($this->listRelations())),
        ], 201);
    }

    public function update(UpdateCaseRequest $request, LegalCase $legalCase): JsonResponse
    {
        $legalCase->load(['caseStatus', 'assignedUsers']);
        $oldStatus = $legalCase->caseStatus?->name;
        $previousIds = $legalCase->assignedUsers->pluck('id')->all();
        $legalCase = $this->persist($request, $legalCase);
        $legalCase->load(['caseStatus', 'assignedUsers']);
        $newStatus = $legalCase->caseStatus?->name;

        if ($oldStatus && $newStatus && $oldStatus !== $newStatus) {
            Auditor::record(
                action: 'status_change',
                module: 'cases',
                subject: $legalCase,
                resourceName: $legalCase->title,
                details: $oldStatus.' → '.$newStatus,
                oldValue: $oldStatus,
                newValue: $newStatus,
            );
            Notifier::caseStatusChanged($legalCase, $oldStatus, $newStatus, $request->user());
        } else {
            Auditor::record(
                action: 'update',
                module: 'cases',
                subject: $legalCase,
                resourceName: $legalCase->title,
                details: 'Case updated',
            );
        }

        $added = $legalCase->assignedUsers->whereNotIn('id', $previousIds);
        Notifier::assignedToCase($legalCase, $added, $request->user());

        return response()->json([
            'message' => 'Case updated.',
            'data' => new CaseResource($legalCase->load($this->listRelations())),
        ]);
    }

    public function archive(LegalCase $legalCase): JsonResponse
    {
        $archived = CaseStatus::query()
            ->where('is_archived', true)
            ->orderBy('sort_order')
            ->first();

        if ($archived) {
            $legalCase->load(['caseStatus', 'assignedUsers']);
            $oldStatus = $legalCase->caseStatus?->name;
            $legalCase->update(['case_status_id' => $archived->id]);
            $legalCase->load('caseStatus');
            Auditor::record(
                action: 'status_change',
                module: 'cases',
                subject: $legalCase,
                resourceName: $legalCase->title,
                details: ($oldStatus ?: 'Active').' → Archived',
                oldValue: $oldStatus,
                newValue: 'Archived',
            );
            Notifier::caseStatusChanged($legalCase, $oldStatus ?: 'Active', 'Archived', request()->user());
        }

        return response()->json([
            'message' => 'Case archived.',
            'data' => new CaseResource($legalCase->load($this->listRelations())),
        ]);
    }

    private function persist(StoreCaseRequest $request, ?LegalCase $legalCase = null): LegalCase
    {
        $data = $request->validated();

        $statusId = $data['case_status_id'] ?? $legalCase?->case_status_id ?? CaseStatus::query()
            ->where('slug', 'open')
            ->value('id');

        $attributes = [
            'client_id' => $data['client_id'],
            'case_number' => $data['case_number'],
            'title' => $data['title'],
            'description' => $data['description'] ?? $legalCase?->description,
            'case_type_id' => $data['case_type_id'] ?? null,
            'case_status_id' => $statusId,
            'court' => $data['court'] ?? null,
        ];

        if ($legalCase) {
            $legalCase->update($attributes);
        } else {
            $attributes['created_by'] = $request->user()->id;
            $legalCase = LegalCase::query()->create($attributes);
        }

        $assigned = $data['assigned_user_ids'] ?? [];
        if ($assigned === []) {
            $assigned = [$request->user()->id];
        }

        $sync = [];
        foreach (array_values($assigned) as $index => $userId) {
            $sync[$userId] = ['is_lead' => $index === 0];
        }
        $legalCase->assignedUsers()->sync($sync);

        if (! empty($data['hearing_at'])) {
            $this->upsertHearing($legalCase, $data['hearing_at'], $request->user()->id);
        }

        return $legalCase->refresh();
    }

    private function upsertHearing(LegalCase $legalCase, string $hearingAt, int $userId): void
    {
        $starts = \Carbon\Carbon::parse($hearingAt);
        if ($starts->format('H:i:s') === '00:00:00') {
            $starts->setTime(10, 0);
        }

        $payload = [
            'title' => $legalCase->title,
            'type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(2),
            'client_id' => $legalCase->client_id,
            'assigned_user_id' => $userId,
            'location' => $legalCase->court,
            'created_by' => $userId,
            'reminder_offset' => '1d',
        ];

        $existing = $legalCase->calendarEvents()
            ->where('status', 'scheduled')
            ->whereIn('type', ['hearing', 'court_mention'])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();

        if ($existing) {
            $existing->update($payload);
        } else {
            $legalCase->calendarEvents()->create($payload);
        }
    }

    /**
     * @return list<string|array<string, callable>>
     */
    private function listRelations(): array
    {
        return [
            'client',
            'caseType',
            'caseStatus',
            'assignedUsers',
            'calendarEvents' => $this->upcomingHearings(),
        ];
    }

    private function loadDetail(LegalCase $legalCase): LegalCase
    {
        return $legalCase->load([
            'client',
            'caseType',
            'caseStatus',
            'createdBy',
            'assignedUsers',
            'calendarEvents' => $this->upcomingHearings(),
            'documents' => fn ($query) => $query->where('is_folder', false)->with('owner')->latest(),
            'tasks.assignee',
        ]);
    }

    private function upcomingHearings(): \Closure
    {
        return function ($query) {
            $query->where('status', 'scheduled')
                ->whereIn('type', ['hearing', 'court_mention'])
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at');
        };
    }

    private function applyFilters($query, Request $request, ?int $userId): void
    {
        $tab = $request->string('tab')->toString();
        $search = trim((string) $request->input('search', ''));

        if ($tab === 'mine' && $userId) {
            $query->whereHas('assignedUsers', fn ($assigned) => $assigned->where('users.id', $userId));
        } elseif ($tab === 'active') {
            $query->whereHas('caseStatus', fn ($status) => $status->where('is_closed', false)->where('is_archived', false));
        } elseif ($tab === 'hearings') {
            $query->whereHas('calendarEvents', function ($events) {
                $events->where('status', 'scheduled')
                    ->whereIn('type', ['hearing', 'court_mention'])
                    ->whereBetween('starts_at', [now(), now()->addDays(7)]);
            });
        } elseif ($tab === 'closed') {
            $query->whereHas('caseStatus', fn ($status) => $status->where('is_closed', true));
        } elseif ($tab === 'archived') {
            $query->whereHas('caseStatus', fn ($status) => $status->where('is_archived', true));
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('case_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('court', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('client')) {
            $query->where('client_id', $request->integer('client'));
        }

        if ($request->filled('case_type')) {
            $query->where('case_type_id', $request->integer('case_type'));
        }

        if ($request->filled('status')) {
            $query->whereHas('caseStatus', fn ($status) => $status->where('slug', $request->input('status')));
        }

        if ($request->filled('court')) {
            $query->where('court', $request->input('court'));
        }
    }

    private function applySort($query, Request $request): void
    {
        $direction = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $sort = $request->string('sort')->toString();

        match ($sort) {
            'caseNumber' => $query->orderBy('case_number', $direction),
            'title' => $query->orderBy('title', $direction),
            'client' => $query->orderBy(
                Client::query()->select('name')->whereColumn('clients.id', 'cases.client_id'),
                $direction,
            ),
            'hearing' => $query->orderBy(
                CalendarEvent::query()
                    ->select('starts_at')
                    ->whereColumn('calendar_events.case_id', 'cases.id')
                    ->where('status', 'scheduled')
                    ->whereIn('type', ['hearing', 'court_mention'])
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->limit(1),
                $direction,
            ),
            'updated' => $query->orderBy('updated_at', $direction),
            default => $query->latest('updated_at'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $base = LegalCase::query();
        $total = (clone $base)->count();
        $active = (clone $base)->whereHas('caseStatus', fn ($status) => $status->where('is_closed', false)->where('is_archived', false))->count();
        $hearingsSoon = (clone $base)->whereHas('calendarEvents', function ($events) {
            $events->where('status', 'scheduled')
                ->whereIn('type', ['hearing', 'court_mention'])
                ->whereBetween('starts_at', [now(), now()->addDays(7)]);
        })->count();
        $closed = (clone $base)->whereHas('caseStatus', fn ($status) => $status->where('is_closed', true))->count();
        $archived = (clone $base)->whereHas('caseStatus', fn ($status) => $status->where('is_archived', true))->count();

        return [
            'total' => $total,
            'active' => $active,
            'hearings_soon' => $hearingsSoon,
            'closed' => $closed,
            'archived' => $archived,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(): array
    {
        return [
            'clients' => Client::query()
                ->where('status', '!=', 'archived')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->name])
                ->values()
                ->all(),
            'case_types' => CaseType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (CaseType $type) => ['id' => $type->id, 'name' => $type->name])
                ->values()
                ->all(),
            'statuses' => CaseStatus::query()
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'name'])
                ->map(fn (CaseStatus $status) => [
                    'id' => $status->id,
                    'value' => $status->slug,
                    'label' => $status->name,
                ])
                ->values()
                ->all(),
            'courts' => LegalCase::query()
                ->whereNotNull('court')
                ->where('court', '!=', '')
                ->distinct()
                ->orderBy('court')
                ->pluck('court')
                ->values()
                ->all(),
        ];
    }
}
