<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientNoteRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientDetailResource;
use App\Http\Resources\ClientResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);

        $query = Client::query()
            ->with(['assignedUsers', 'primaryContact'])
            ->withCount($this->caseCounts())
            ->latest('updated_at');

        $this->applyFilters($query, $request, $user?->id);

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => ClientResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'stats' => $this->stats(),
            'lawyers' => $this->firmLawyers($user?->firm_id),
        ]);
    }

    public function show(Client $client): ClientDetailResource
    {
        $client->load([
            'assignedUsers',
            'primaryContact',
            'contacts',
            'clientNotes',
            'cases.caseStatus',
            'cases.calendarEvents' => fn ($query) => $query
                ->where('status', 'scheduled')
                ->whereIn('type', ['hearing', 'court_mention'])
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at'),
            'documents' => fn ($query) => $query->where('is_folder', false)->latest('updated_at'),
        ])->loadCount($this->caseCounts());

        $client->setAttribute('activity_items', AuditLog::query()
            ->where('subject_type', 'clients')
            ->where('subject_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AuditLog $row) => [
                'id' => $row->id,
                'client_id' => $client->id,
                'text' => $row->details ?: ($row->resource_name ? "{$row->action} · {$row->resource_name}" : $row->action),
                'time' => optional($row->created_at)?->toIso8601String(),
            ])
            ->all());

        return new ClientDetailResource($client);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->persist($request);

        Auditor::record(
            action: 'create',
            module: 'clients',
            subject: $client,
            resourceName: $client->name,
            details: 'New client record created',
        );

        return response()->json([
            'message' => 'Client created.',
            'data' => new ClientResource(
                $client->load(['assignedUsers', 'primaryContact'])->loadCount($this->caseCounts())
            ),
        ], 201);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $oldPhone = $client->phone;
        $client = $this->persist($request, $client);

        Auditor::record(
            action: 'update',
            module: 'clients',
            subject: $client,
            resourceName: $client->name,
            details: 'Client record updated',
            oldValue: $oldPhone !== $client->phone ? $oldPhone : null,
            newValue: $oldPhone !== $client->phone ? $client->phone : null,
            oldLabel: $oldPhone !== $client->phone ? 'Previous Phone' : null,
            newLabel: $oldPhone !== $client->phone ? 'New Phone' : null,
        );

        return response()->json([
            'message' => 'Client updated.',
            'data' => new ClientResource(
                $client->load(['assignedUsers', 'primaryContact'])->loadCount($this->caseCounts())
            ),
        ]);
    }

    public function archive(Client $client): JsonResponse
    {
        $client->archive();

        Auditor::record(
            action: 'update',
            module: 'clients',
            subject: $client,
            resourceName: $client->name,
            details: 'Client archived',
            oldValue: 'Active',
            newValue: 'Archived',
        );

        return response()->json([
            'message' => 'Client archived.',
            'data' => new ClientResource(
                $client->load(['assignedUsers', 'primaryContact'])->loadCount($this->caseCounts())
            ),
        ]);
    }

    public function storeNote(StoreClientNoteRequest $request, Client $client): JsonResponse
    {
        $note = $client->clientNotes()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('text'),
        ]);

        $client->touch();

        return response()->json([
            'message' => 'Note added.',
            'data' => [
                'id' => $note->id,
                'client_id' => $client->id,
                'text' => $note->body,
                'created_at' => $note->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function persist(StoreClientRequest $request, ?Client $client = null): Client
    {
        $data = $request->validated();
        $name = $data['type'] === 'individual'
            ? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''))
            : (string) $data['name'];

        $attributes = [
            'type' => $data['type'],
            'status' => $data['status'] ?? 'active',
            'name' => $name,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'industry' => $data['industry'] ?? null,
            'tin' => $data['tin'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($client) {
            $client->update($attributes);
        } else {
            $attributes['created_by'] = $request->user()->id;
            $client = Client::query()->create($attributes);
        }

        $contactName = $data['type'] === 'company'
            ? ($data['primary_contact'] ?? $name)
            : $name;

        $primary = $client->contacts()->where('is_primary', true)->first();
        $contactPayload = [
            'name' => $contactName,
            'title' => $data['type'] === 'company' ? 'Primary Contact' : 'Client',
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_primary' => true,
        ];

        if ($primary) {
            $primary->update($contactPayload);
        } else {
            $client->contacts()->create($contactPayload);
        }

        $assigned = $data['assigned_user_ids'] ?? [];
        if ($assigned === []) {
            $assigned = [$request->user()->id];
        }
        $client->assignedUsers()->sync($assigned);

        if (! $client->wasRecentlyCreated && empty($data['notes'])) {
            return $client->refresh();
        }

        if (! empty($data['notes']) && $client->wasRecentlyCreated) {
            $client->clientNotes()->create([
                'user_id' => $request->user()->id,
                'body' => $data['notes'],
            ]);
        }

        return $client->refresh();
    }

    /**
     * @return array<string, callable>
     */
    private function caseCounts(): array
    {
        $open = function ($query) {
            $query->where(function ($query) {
                $query->whereDoesntHave('caseStatus')
                    ->orWhereHas('caseStatus', fn ($status) => $status->where('is_closed', false)->where('is_archived', false));
            });
        };

        return [
            'cases as open_cases_count' => $open,
            'cases as closed_cases_count' => fn ($query) => $query->whereHas('caseStatus', fn ($status) => $status->where('is_closed', true)),
            'documents as documents_count' => fn ($query) => $query->where('is_folder', false),
        ];
    }

    private function applyFilters($query, Request $request, ?int $userId): void
    {
        $tab = $request->string('tab')->toString();
        $search = trim((string) $request->input('search', ''));

        if ($tab === 'mine' && $userId) {
            $query->whereHas('assignedUsers', fn ($assigned) => $assigned->where('users.id', $userId));
        } elseif ($tab === 'companies') {
            $query->where('type', 'company');
        } elseif ($tab === 'individuals') {
            $query->where('type', 'individual');
        } elseif ($tab === 'active') {
            $query->where('status', 'active');
        } elseif ($tab === 'archived') {
            $query->where('status', 'archived');
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('contacts', fn ($contacts) => $contacts->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('lawyer')) {
            $query->whereHas('assignedUsers', fn ($assigned) => $assigned->where('users.id', $request->integer('lawyer')));
        }

        if ($request->input('case_status') === 'open') {
            $query->whereHas('cases', $this->caseCounts()['cases as open_cases_count']);
        }

        if ($request->input('case_status') === 'none') {
            $query->whereDoesntHave('cases', $this->caseCounts()['cases as open_cases_count']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $base = Client::query();
        $total = (clone $base)->count();
        $active = (clone $base)->where('status', 'active')->count();
        $newThisMonth = (clone $base)->where('created_at', '>=', now()->startOfMonth())->count();
        $withOpenCases = (clone $base)->whereHas('cases', $this->caseCounts()['cases as open_cases_count'])->count();

        return [
            'total' => $total,
            'active' => $active,
            'new_this_month' => $newThisMonth,
            'with_open_cases' => $withOpenCases,
        ];
    }

    /**
     * @return list<array{id: int, name: string, initials: string}>
     */
    private function firmLawyers(?int $firmId): array
    {
        if (! $firmId) {
            return [];
        }

        return User::query()
            ->where('firm_id', $firmId)
            ->where('status', '!=', 'inactive')
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'initials' => $user->initials,
            ])
            ->values()
            ->all();
    }
}
