<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Api\Concerns\AuthorizesPlatformAdmin;
use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Models\User;
use App\Services\Auditor;
use App\Services\FirmProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class FirmController extends Controller
{
    use AuthorizesPlatformAdmin;

    public function __construct(private FirmProvisioner $provisioner) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);
        $search = trim((string) $request->input('search', ''));
        $status = $request->string('status')->toString();

        $query = Firm::query()->withCount(['users', 'legalCases', 'clients'])->latest();

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('status', 'active')->whereNull('deactivated_at');
        } elseif ($status === 'inactive') {
            $query->where(function ($query) {
                $query->where('status', '!=', 'active')->orWhereNotNull('deactivated_at');
            });
        }

        $page = $query->paginate($perPage);
        $base = Firm::query();

        return response()->json([
            'data' => collect($page->items())->map(fn (Firm $firm) => $this->serialize($firm))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'stats' => [
                'firms' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->whereNull('deactivated_at')->count(),
                'inactive' => (clone $base)->where(function ($query) {
                    $query->where('status', '!=', 'active')->orWhereNotNull('deactivated_at');
                })->count(),
            ],
        ]);
    }

    public function show(Request $request, Firm $firm): JsonResponse
    {
        $this->authorizePlatform($request);

        $firm->loadCount(['users', 'legalCases', 'clients', 'documents']);
        $users = User::query()
            ->with('role')
            ->where('firm_id', $firm->id)
            ->where('is_platform_admin', false)
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'data' => array_merge($this->serialize($firm, true), [
                'users' => $users->map(fn (User $user) => $this->serializeUser($user))->values(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'admin_first_name' => ['required', 'string', 'max:80'],
            'admin_last_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'admin_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $firm = DB::transaction(function () use ($data) {
            $firm = $this->provisioner->createFirm($data);
            $this->provisioner->createMember($firm, [
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'email' => $data['admin_email'],
                'phone' => $data['admin_phone'] ?? null,
                'access_role' => 'administrator',
                'job_role' => 'managing_partner',
            ]);

            return $firm->fresh()->loadCount(['users', 'legalCases', 'clients']);
        });

        Auditor::record(
            action: 'create',
            module: 'team',
            subject: $firm,
            resourceName: $firm->name,
            details: 'Firm created from platform admin',
            firmId: $firm->id,
        );

        return response()->json([
            'message' => 'Firm created. The administrator can sign in with the default password.',
            'data' => $this->serialize($firm, true),
        ], 201);
    }

    public function update(Request $request, Firm $firm): JsonResponse
    {
        $this->authorizePlatform($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'registration_number' => ['nullable', 'string', 'max:80'],
        ]);

        $firm->update($data);
        $firm->loadCount(['users', 'legalCases', 'clients']);

        Auditor::record(
            action: 'update',
            module: 'team',
            subject: $firm,
            resourceName: $firm->name,
            details: 'Firm profile updated from platform admin',
            firmId: $firm->id,
        );

        return response()->json([
            'message' => 'Firm updated.',
            'data' => $this->serialize($firm, true),
        ]);
    }

    public function deactivate(Request $request, Firm $firm): JsonResponse
    {
        $this->authorizePlatform($request);

        $firm->update([
            'status' => 'inactive',
            'deactivated_at' => now(),
        ]);

        $ids = User::query()->where('firm_id', $firm->id)->pluck('id');
        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $ids)
            ->delete();

        Auditor::record(
            action: 'update',
            module: 'security',
            subject: $firm,
            resourceName: $firm->name,
            details: 'Workspace deactivated from platform admin',
            firmId: $firm->id,
        );

        $firm->loadCount(['users', 'legalCases', 'clients']);

        return response()->json([
            'message' => 'Firm deactivated. Members cannot sign in until it is reactivated.',
            'data' => $this->serialize($firm, true),
        ]);
    }

    public function activate(Request $request, Firm $firm): JsonResponse
    {
        $this->authorizePlatform($request);

        $firm->update([
            'status' => 'active',
            'deactivated_at' => null,
        ]);

        Auditor::record(
            action: 'update',
            module: 'security',
            subject: $firm,
            resourceName: $firm->name,
            details: 'Workspace reactivated from platform admin',
            firmId: $firm->id,
        );

        $firm->loadCount(['users', 'legalCases', 'clients']);

        return response()->json([
            'message' => 'Firm reactivated.',
            'data' => $this->serialize($firm, true),
        ]);
    }

    public function storeUser(Request $request, Firm $firm): JsonResponse
    {
        $this->authorizePlatform($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_role' => ['nullable', 'string', 'max:80'],
            'access_role' => ['nullable', Rule::exists('roles', 'slug')->where('firm_id', $firm->id)],
        ]);

        $member = $this->provisioner->createMember($firm, $data);

        Auditor::record(
            action: 'create',
            module: 'team',
            subject: $member,
            resourceName: $member->name,
            details: 'Team member added from platform admin',
            firmId: $firm->id,
        );

        return response()->json([
            'message' => 'Member added. They can sign in with the default password.',
            'data' => $this->serializeUser($member),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Firm $firm, bool $detail = false): array
    {
        $row = [
            'id' => $firm->id,
            'name' => $firm->name,
            'slug' => $firm->slug,
            'email' => $firm->email,
            'phone' => $firm->phone,
            'city' => $firm->city,
            'country' => $firm->country,
            'status' => $firm->isActive() ? 'active' : ($firm->status === 'deleted' ? 'deleted' : 'inactive'),
            'users_count' => (int) ($firm->users_count ?? 0),
            'cases_count' => (int) ($firm->legal_cases_count ?? 0),
            'clients_count' => (int) ($firm->clients_count ?? 0),
            'created_at' => optional($firm->created_at)?->toIso8601String(),
        ];

        if (! $detail) {
            return $row;
        }

        return array_merge($row, [
            'website' => $firm->website,
            'address' => $firm->address,
            'registration_number' => $firm->registration_number,
            'documents_count' => (int) ($firm->documents_count ?? 0),
            'deactivated_at' => optional($firm->deactivated_at)?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_role' => $user->job_role,
            'status' => $user->status,
            'initials' => $user->initials,
            'role' => $user->role?->title,
            'role_slug' => $user->role?->slug,
            'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
        ];
    }
}
