<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Api\Concerns\AuthorizesPlatformAdmin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use AuthorizesPlatformAdmin;

    public function show(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $firms = Firm::query();
        $users = User::query()->where('is_platform_admin', false);
        $cases = LegalCase::withoutGlobalScope('firm');

        $recentFirms = Firm::query()
            ->withCount(['users', 'legalCases', 'clients'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Firm $firm) => $this->firmRow($firm))
            ->values();

        $activity = AuditLog::withoutGlobalScope('firm')
            ->with(['user', 'firm'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->user?->name ?: 'Someone',
                'action' => $log->action,
                'details' => $log->details,
                'resource_name' => $log->resource_name,
                'firm' => $log->firm?->name,
                'occurred_at' => optional($log->created_at)?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'stats' => [
                'firms' => (clone $firms)->count(),
                'active_firms' => (clone $firms)->where('status', 'active')->whereNull('deactivated_at')->count(),
                'users' => (clone $users)->count(),
                'cases' => (clone $cases)->count(),
            ],
            'firms' => $recentFirms,
            'activity' => $activity,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function firmRow(Firm $firm): array
    {
        return [
            'id' => $firm->id,
            'name' => $firm->name,
            'slug' => $firm->slug,
            'city' => $firm->city,
            'country' => $firm->country,
            'status' => $firm->isActive() ? 'active' : ($firm->status ?: 'inactive'),
            'users_count' => (int) ($firm->users_count ?? 0),
            'cases_count' => (int) ($firm->legal_cases_count ?? 0),
            'clients_count' => (int) ($firm->clients_count ?? 0),
            'created_at' => optional($firm->created_at)?->toIso8601String(),
        ];
    }
}
