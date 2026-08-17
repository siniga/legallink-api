<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        $query = AuditLog::query()->with(['user.role'])->latest('created_at');
        $this->applyFilters($query, $request);

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'stats' => $this->stats(),
            'lookups' => $this->lookups($user),
        ]);
    }

    public function show(AuditLog $auditLog): AuditLogResource
    {
        return new AuditLogResource($auditLog->load(['user.role']));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = AuditLog::query()->with(['user.role'])->latest('created_at');
        $this->applyFilters($query, $request);

        $filename = 'activity-logs-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Time', 'User', 'Action', 'Module', 'Record', 'Details', 'IP Address', 'Device']);

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    $resource = (new AuditLogResource($log))->resolve();
                    fputcsv($handle, [
                        optional($log->created_at)?->toDateString(),
                        optional($log->created_at)?->format('H:i:s'),
                        $resource['user']['name'] ?? 'Unknown',
                        $resource['action'],
                        $resource['module'],
                        $resource['resource_name'],
                        $resource['details'],
                        $resource['ip_address'],
                        $resource['device'],
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        $tab = $request->string('tab')->toString();
        $search = trim((string) $request->input('search', ''));

        if ($tab === 'logins') {
            $query->whereIn('action', ['login', 'failed_login', 'new_device_login']);
        } elseif ($tab === 'security') {
            $query->where('module', 'security');
        } elseif (in_array($tab, ['cases', 'documents', 'clients', 'tasks', 'team'], true)) {
            $query->where('module', $tab);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('resource_name', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($users) => $users
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('ip')) {
            $query->where('ip_address', 'like', '%'.trim((string) $request->input('ip')).'%');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $base = AuditLog::query();

        $todayCount = (clone $base)->whereDate('created_at', $today)->count();
        $yesterdayCount = (clone $base)->whereDate('created_at', $yesterday)->count();
        $logins = (clone $base)->whereDate('created_at', $today)->whereIn('action', ['login', 'new_device_login'])->count();
        $newDevices = (clone $base)->whereDate('created_at', $today)->where('action', 'new_device_login')->count();
        $documents = (clone $base)->whereDate('created_at', $today)
            ->where('module', 'documents')
            ->whereIn('action', ['upload', 'download', 'share', 'create', 'update', 'delete'])
            ->count();
        $security = (clone $base)->whereDate('created_at', $today)->where('module', 'security')
            ->whereIn('action', ['failed_login', 'new_device_login', 'permission_change', 'password_change', 'session_revoked'])
            ->count();

        $delta = $yesterdayCount === 0
            ? ($todayCount > 0 ? 100 : 0)
            : (int) round((($todayCount - $yesterdayCount) / $yesterdayCount) * 100);

        return [
            'today' => $todayCount,
            'today_delta' => $delta,
            'logins' => $logins,
            'new_devices' => $newDevices,
            'documents' => $documents,
            'security' => $security,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(?User $user): array
    {
        if (! $user?->firm_id) {
            return ['users' => []];
        }

        return [
            'users' => User::query()
                ->with('role')
                ->where('firm_id', $user->firm_id)
                ->where('is_platform_admin', false)
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'role' => $member->role?->title ?: $member->job_role,
                    'initials' => $member->initials,
                ])
                ->values()
                ->all(),
        ];
    }
}
