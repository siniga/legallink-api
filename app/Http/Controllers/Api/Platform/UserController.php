<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Api\Concerns\AuthorizesPlatformAdmin;
use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use AuthorizesPlatformAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);
        $search = trim((string) $request->input('search', ''));

        $query = User::query()
            ->with(['firm', 'role'])
            ->where('is_platform_admin', false)
            ->latest();

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereHas('firm', fn ($firms) => $firms->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('firm_id')) {
            $query->where('firm_id', $request->integer('firm_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $page = $query->paginate($perPage);
        $base = User::query()->where('is_platform_admin', false);

        return response()->json([
            'data' => collect($page->items())->map(fn (User $user) => $this->serialize($user))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'inactive' => (clone $base)->where('status', 'inactive')->count(),
            ],
            'lookups' => [
                'firms' => Firm::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Firm $firm) => ['id' => $firm->id, 'name' => $firm->name])
                    ->values(),
            ],
        ]);
    }

    public function deactivate(Request $request, User $member): JsonResponse
    {
        $this->authorizePlatform($request);
        $this->guardMember($member);

        $member->update([
            'status' => 'inactive',
            'deactivated_at' => now(),
        ]);
        $member->tokens()->delete();

        Auditor::record(
            action: 'update',
            module: 'team',
            subject: $member,
            resourceName: $member->name,
            details: 'Member deactivated from platform admin',
            firmId: $member->firm_id,
        );

        return response()->json([
            'message' => 'Member deactivated.',
            'data' => $this->serialize($member->load(['firm', 'role'])),
        ]);
    }

    public function activate(Request $request, User $member): JsonResponse
    {
        $this->authorizePlatform($request);
        $this->guardMember($member);

        $member->update([
            'status' => 'active',
            'deactivated_at' => null,
        ]);

        Auditor::record(
            action: 'update',
            module: 'team',
            subject: $member,
            resourceName: $member->name,
            details: 'Member reactivated from platform admin',
            firmId: $member->firm_id,
        );

        return response()->json([
            'message' => 'Member reactivated.',
            'data' => $this->serialize($member->load(['firm', 'role'])),
        ]);
    }

    private function guardMember(User $member): void
    {
        if ($member->is_platform_admin || ! $member->firm_id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_role' => $user->job_role,
            'status' => $user->status,
            'initials' => $user->initials,
            'firm_id' => $user->firm_id,
            'firm' => $user->firm?->name,
            'role' => $user->role?->title,
            'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
        ];
    }
}
