<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auditor;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);

        $query = $this->members($user)->with('role')->withCount($this->counts());
        $this->applyFilters($query, $request);
        $query->orderBy('first_name')->orderBy('last_name');

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => TeamMemberResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'stats' => $this->stats($user),
            'lookups' => $this->lookups($user),
        ]);
    }

    public function show(Request $request, User $member): TeamMemberResource
    {
        $this->authorizeMember($request, $member);

        $member->load(['role.permissions', 'assignedCases.caseStatus'])
            ->loadCount($this->counts())
            ->load([
                'assignedTasks' => fn ($query) => $query->latest('updated_at')->limit(20),
            ]);

        return new TeamMemberResource($member);
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $member = $this->persist($request);

        Auditor::record(
            action: 'create',
            module: 'team',
            subject: $member,
            resourceName: $member->name,
            details: 'Team member added',
        );
        Notifier::memberAdded($member, $request->user());

        return response()->json([
            'message' => 'Team member added. They can sign in with the default password.',
            'data' => new TeamMemberResource($member->load('role')->loadCount($this->counts())),
        ], 201);
    }

    public function update(UpdateTeamMemberRequest $request, User $member): JsonResponse
    {
        $this->authorizeMember($request, $member);
        $member->load('role');
        $oldRole = $member->role?->title ?: $member->job_role;
        $member = $this->persist($request, $member)->load('role');
        $newRole = $member->role?->title ?: $member->job_role;
        $roleChanged = $oldRole !== $newRole;

        Auditor::record(
            action: $roleChanged ? 'permission_change' : 'update',
            module: $roleChanged ? 'security' : 'team',
            subject: $member,
            resourceName: $member->name,
            details: $roleChanged ? 'Role changed from '.$oldRole.' to '.$newRole : 'Team member updated',
            oldValue: $roleChanged ? $oldRole : null,
            newValue: $roleChanged ? $newRole : null,
            oldLabel: $roleChanged ? 'Previous Role' : null,
            newLabel: $roleChanged ? 'New Role' : null,
        );

        if ($roleChanged) {
            Notifier::permissionChanged($member, 'Role changed from '.$oldRole.' to '.$newRole, $request->user());
        }

        return response()->json([
            'message' => 'Team member updated.',
            'data' => new TeamMemberResource($member->load('role')->loadCount($this->counts())),
        ]);
    }

    public function deactivate(Request $request, User $member): JsonResponse
    {
        $this->authorizeMember($request, $member);

        if ((int) $member->id === (int) $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $inactive = $member->status !== 'inactive';
        $member->update([
            'status' => $inactive ? 'inactive' : 'active',
            'deactivated_at' => $inactive ? now() : null,
        ]);

        if ($inactive) {
            $member->tokens()->delete();
        }

        Auditor::record(
            action: $inactive ? 'session_revoked' : 'update',
            module: $inactive ? 'security' : 'team',
            subject: $member,
            resourceName: $member->name,
            details: $inactive ? 'Session revoked after member deactivation' : 'Team member reactivated',
        );

        return response()->json([
            'message' => $inactive ? 'Team member deactivated.' : 'Team member reactivated.',
            'data' => new TeamMemberResource($member->load('role')->loadCount($this->counts())),
        ]);
    }

    public function updatePermission(Request $request, User $member): JsonResponse
    {
        $this->authorizeMember($request, $member);

        $data = $request->validate([
            'permission' => ['required', 'string', 'exists:permissions,slug'],
            'enabled' => ['required', 'boolean'],
        ]);

        $role = $member->role;
        if (! $role) {
            return response()->json(['message' => 'This member has no access role.'], 422);
        }

        $permissionId = Permission::query()->where('slug', $data['permission'])->value('id');
        if ($request->boolean('enabled')) {
            $role->permissions()->syncWithoutDetaching([$permissionId]);
        } else {
            $role->permissions()->detach($permissionId);
        }

        $member->load(['role.permissions'])->loadCount($this->counts());

        Auditor::record(
            action: 'permission_change',
            module: 'security',
            subject: $member,
            resourceName: $member->name,
            details: ($data['enabled'] ? 'Granted' : 'Revoked').' '.$data['permission'].' on '.$role->title,
        );
        Notifier::permissionChanged(
            $member,
            ($data['enabled'] ? 'Granted' : 'Revoked').' '.$data['permission'],
            $request->user(),
        );

        return response()->json([
            'message' => 'Permissions updated.',
            'data' => new TeamMemberResource($member),
        ]);
    }

    private function persist(StoreTeamMemberRequest $request, ?User $member = null): User
    {
        $data = $request->validated();
        $user = $request->user();
        $first = $data['first_name'] ?? $member?->first_name;
        $last = $data['last_name'] ?? $member?->last_name;
        $status = $data['status'] ?? $member?->status ?? 'active';

        $roleId = $member?->role_id;
        if (! empty($data['access_role'])) {
            $roleId = Role::query()
                ->where('firm_id', $user->firm_id)
                ->where('slug', $data['access_role'])
                ->value('id') ?: $roleId;
        }

        $attributes = [
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($first.' '.$last),
            'email' => $data['email'] ?? $member?->email,
            'phone' => array_key_exists('phone', $data) ? ($data['phone'] ?: null) : $member?->phone,
            'job_role' => $data['job_role'] ?? $member?->job_role,
            'role_id' => $roleId,
            'status' => $status,
            'deactivated_at' => $status === 'inactive' ? ($member?->deactivated_at ?? now()) : null,
        ];

        if ($member) {
            $member->update($attributes);
        } else {
            $attributes['firm_id'] = $user->firm_id;
            $attributes['password'] = 'password';
            $attributes['remember_token'] = Str::random(10);
            $attributes['joined_at'] = now()->toDateString();
            $member = User::query()->create($attributes);
        }

        return $member->refresh();
    }

    /**
     * @return list<string|array<string, callable>>
     */
    private function counts(): array
    {
        return [
            'assignedCases as active_cases_count' => fn ($query) => $query
                ->whereHas('caseStatus', fn ($status) => $status->where('is_closed', false)->where('is_archived', false)),
            'assignedTasks as open_tasks_count' => fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled']),
            'assignedTasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
            'ownedDocuments as documents_shared_count' => fn ($query) => $query->where('is_folder', false),
        ];
    }

    private function members(User $user)
    {
        return User::query()
            ->where('firm_id', $user->firm_id)
            ->where('is_platform_admin', false);
    }

    private function authorizeMember(Request $request, User $member): void
    {
        $user = $request->user();
        if (! $user?->firm_id || (int) $member->firm_id !== (int) $user->firm_id || $member->is_platform_admin) {
            abort(404);
        }
    }

    private function applyFilters($query, Request $request): void
    {
        $tab = $request->string('tab')->toString();
        $search = trim((string) $request->input('search', ''));

        if ($tab === 'partners') {
            $query->whereIn('job_role', ['managing_partner', 'partner']);
        } elseif ($tab === 'lawyers') {
            $query->whereIn('job_role', ['senior_associate', 'associate']);
        } elseif ($tab === 'paralegals') {
            $query->where('job_role', 'paralegal');
        } elseif ($tab === 'support') {
            $query->whereIn('job_role', ['legal_assistant', 'finance_admin']);
        } elseif ($tab === 'inactive') {
            $query->where('status', 'inactive');
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('job_role', 'like', "%{$search}%");
            });
        }

        if ($request->filled('job_role')) {
            $query->where('job_role', $request->input('job_role'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('access_role')) {
            $query->whereHas('role', fn ($role) => $role->where('slug', $request->input('access_role')));
        }
        if ($request->filled('workload')) {
            $this->applyWorkloadFilter($query, $request->input('workload'));
        }
    }

    private function applyWorkloadFilter($query, string $workload): void
    {
        $openTasks = 'select count(*) from tasks where tasks.assignee_id = users.id and tasks.deleted_at is null and tasks.status not in (\'completed\', \'cancelled\')';
        $activeCases = 'select count(*) from case_user inner join cases on cases.id = case_user.case_id inner join case_statuses on case_statuses.id = cases.case_status_id where case_user.user_id = users.id and cases.deleted_at is null and case_statuses.is_closed = 0 and case_statuses.is_archived = 0';

        match ($workload) {
            'high' => $query->whereRaw("(($openTasks) >= 6 or ($activeCases) >= 10)"),
            'medium' => $query->whereRaw("(($openTasks) >= 3 or ($activeCases) >= 5) and not (($openTasks) >= 6 or ($activeCases) >= 10)"),
            'low' => $query->whereRaw("($openTasks) < 3 and ($activeCases) < 5"),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(User $user): array
    {
        $base = $this->members($user);
        $total = (clone $base)->count();
        $lawyers = (clone $base)->whereIn('job_role', ['managing_partner', 'partner', 'senior_associate', 'associate'])->count();
        $support = (clone $base)->whereIn('job_role', ['paralegal', 'legal_assistant', 'finance_admin', 'intern'])->count();
        $active = (clone $base)->where('status', 'active')->count();
        $away = (clone $base)->whereIn('status', ['away', 'inactive'])->count();

        return [
            'total' => $total,
            'lawyers' => $lawyers,
            'support' => $support,
            'active' => $active,
            'away' => $away,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(User $user): array
    {
        return [
            'access_roles' => Role::query()
                ->where('firm_id', $user->firm_id)
                ->orderBy('sort_order')
                ->get(['slug', 'title'])
                ->map(fn (Role $role) => [
                    'id' => $role->slug,
                    'title' => $role->title,
                ])
                ->values()
                ->all(),
        ];
    }
}
