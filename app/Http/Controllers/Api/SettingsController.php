<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseStatus;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\Document;
use App\Models\Firm;
use App\Models\LegalCase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_role' => ['nullable', 'string', 'max:40'],
        ]);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'job_role' => $data['job_role'] ?? $user->job_role,
        ]);

        Auditor::record('update', 'team', $user, $user->name, 'Profile updated');

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $this->payload($user->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $prefs = $user->preferences ?? [];
        $prefs['password_changed_at'] = now()->toIso8601String();
        $user->update([
            'password' => $data['password'],
            'preferences' => $prefs,
        ]);

        $current = $user->currentAccessToken();
        $user->tokens()->when($current, fn ($query) => $query->where('id', '!=', $current->id))->delete();

        Auditor::record('password_change', 'security', $user, 'Account Security', 'Password updated successfully');

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function updateFirm(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $firm = $this->firm($request);
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
        Auditor::record('update', 'team', $firm, $firm->name, 'Firm profile updated');

        return response()->json([
            'message' => 'Firm profile updated successfully',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateDocuments(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $firm = $this->firm($request);
        $data = $request->validate([
            'default_visibility' => ['required', Rule::in(['private', 'team', 'ask'])],
            'folder_visibility' => ['required', Rule::in(['private', 'team'])],
            'allow_create_folders' => ['required', 'boolean'],
            'allow_sharing' => ['required', 'boolean'],
            'allow_downloads' => ['required', 'boolean'],
            'organize_by_client' => ['required', 'boolean'],
            'create_default_folders' => ['required', 'boolean'],
            'max_upload_mb' => ['nullable', 'integer', 'min:1', 'max:1024'],
            'allowed_types' => ['nullable', 'array'],
            'default_folders' => ['nullable', 'array'],
        ]);

        $firm->update(['document_settings' => array_merge($this->defaultDocumentSettings(), $data)]);
        Auditor::record('update', 'documents', $firm, 'Document settings', 'Document preferences updated');

        return response()->json([
            'message' => 'Document settings saved',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateCases(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $firm = $this->firm($request);
        $data = $request->validate([
            'case_number_format' => ['required', 'string', 'max:80'],
        ]);

        $firm->update(['case_number_format' => $data['case_number_format']]);
        Auditor::record('update', 'cases', $firm, 'Case settings', 'Case number format updated');

        return response()->json([
            'message' => 'Case settings saved',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function storeCaseStatus(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:40'],
        ]);

        $slug = Str::slug($data['name']) ?: 'status';
        $base = $slug;
        $i = 2;
        while (CaseStatus::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $status = CaseStatus::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'color' => $data['color'] ?: 'bg-slate-400',
            'sort_order' => (int) CaseStatus::query()->max('sort_order') + 1,
            'is_closed' => false,
            'is_archived' => false,
        ]);

        return response()->json([
            'message' => 'Case status added',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ], 201);
    }

    public function reorderCaseStatuses(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
        ]);

        $statuses = CaseStatus::query()->get();
        $order = 1;
        $seen = [];

        DB::transaction(function () use ($data, $statuses, &$order, &$seen) {
            foreach ($data['ids'] as $value) {
                $status = $statuses->first(fn (CaseStatus $item) => $item->slug === $value || (string) $item->id === $value);
                if (! $status || isset($seen[$status->id])) {
                    continue;
                }
                $status->update(['sort_order' => $order++]);
                $seen[$status->id] = true;
            }

            foreach ($statuses as $status) {
                if (! isset($seen[$status->id])) {
                    $status->update(['sort_order' => $order++]);
                }
            }
        });

        return response()->json([
            'message' => 'Case status order saved',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateCaseStatus(Request $request, string $status): JsonResponse
    {
        $this->authorizeManage($request);
        $caseStatus = $this->findStatus($status);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:40'],
        ]);

        $caseStatus->update([
            'name' => $data['name'],
            'color' => $data['color'] ?: $caseStatus->color,
        ]);

        return response()->json([
            'message' => 'Case status updated',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function destroyCaseStatus(Request $request, string $status): JsonResponse
    {
        $this->authorizeManage($request);
        $caseStatus = $this->findStatus($status);

        if (CaseStatus::query()->count() <= 1) {
            return response()->json(['message' => 'You must keep at least one case status.'], 422);
        }

        if ($caseStatus->is_archived) {
            return response()->json(['message' => 'The archived status cannot be deleted.'], 422);
        }

        $fallback = CaseStatus::query()
            ->where('id', '!=', $caseStatus->id)
            ->orderBy('sort_order')
            ->first();

        if ($fallback) {
            LegalCase::query()->where('case_status_id', $caseStatus->id)->update([
                'case_status_id' => $fallback->id,
            ]);
        }

        $caseStatus->delete();

        return response()->json([
            'message' => 'Case status deleted',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function storeCaseType(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $name = $data['name'];
        $base = $name;
        $i = 2;
        while (CaseType::query()->where('name', $name)->exists()) {
            $name = $base.' '.$i++;
        }

        $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'OTH', 0, 4));
        $type = CaseType::query()->create([
            'name' => $name,
            'code' => $code,
            'sort_order' => (int) CaseType::query()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Case type added',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ], 201);
    }

    public function destroyCaseType(Request $request, string $type): JsonResponse
    {
        $this->authorizeManage($request);
        $caseType = CaseType::query()
            ->where(function ($query) use ($type) {
                $query->whereKey($type)->orWhere('name', $type);
            })
            ->first();
        if (! $caseType) {
            abort(404);
        }

        if (CaseType::query()->count() <= 1) {
            return response()->json(['message' => 'You must keep at least one case type.'], 422);
        }

        LegalCase::query()->where('case_type_id', $caseType->id)->update(['case_type_id' => null]);
        $caseType->delete();

        return response()->json([
            'message' => 'Case type deleted',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'flags' => ['required', 'array'],
            'hearing_reminder' => ['required', 'string', 'max:8'],
            'task_reminder' => ['required', 'string', 'max:8'],
        ]);

        $user->update([
            'notification_preferences' => [
                'flags' => $data['flags'],
                'hearing_reminder' => $data['hearing_reminder'],
                'task_reminder' => $data['task_reminder'],
            ],
        ]);

        return response()->json([
            'message' => 'Notification preferences saved',
            'data' => $this->payload($user->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateAppearance(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'density' => ['required', Rule::in(['comfortable', 'compact'])],
            'sidebar' => ['required', Rule::in(['expanded', 'compact'])],
            'date_format' => ['required', Rule::in(['ddmmyyyy', 'mmddyyyy', 'yyyymmdd'])],
            'time_format' => ['required', Rule::in(['12h', '24h'])],
        ]);

        $prefs = $user->preferences ?? [];
        $user->update(['preferences' => array_merge($prefs, $data)]);

        return response()->json([
            'message' => 'Appearance preferences saved',
            'data' => $this->payload($user->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateSecurity(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'two_factor' => ['sometimes', 'boolean'],
            'alerts' => ['required', 'array'],
            'alerts.device' => ['required', 'boolean'],
            'alerts.failed' => ['required', 'boolean'],
            'alerts.changes' => ['required', 'boolean'],
        ]);

        $prefs = $user->preferences ?? [];
        $prefs['login_alerts'] = $data['alerts'];

        if (array_key_exists('two_factor', $data)) {
            if ($data['two_factor'] && ! $user->two_factor_confirmed_at) {
                $user->two_factor_secret = Str::random(32);
                $user->two_factor_confirmed_at = now();
            } elseif (! $data['two_factor']) {
                $user->two_factor_secret = null;
                $user->two_factor_confirmed_at = null;
            }
        }

        $user->preferences = $prefs;
        $user->save();

        Auditor::record('update', 'security', $user, 'Account Security', 'Security preferences updated');

        return response()->json([
            'message' => 'Security preferences saved',
            'data' => $this->payload($user->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManage($request);
        $user = $request->user();
        if ((int) $role->firm_id !== (int) $user->firm_id) {
            abort(404);
        }

        $data = $request->validate([
            'permissions' => ['required', 'array'],
        ]);

        $enabled = collect($data['permissions'])
            ->filter(fn ($value) => (bool) $value)
            ->keys()
            ->all();

        $ids = Permission::query()->whereIn('slug', $enabled)->pluck('id');
        $role->permissions()->sync($ids);

        Auditor::record(
            'permission_change',
            'security',
            $role,
            $role->title,
            'Role permissions updated',
        );

        return response()->json([
            'message' => $role->title.' permissions updated',
            'data' => $this->payload($user->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function revokeSession(Request $request, string $session): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->whereKey($session)->first();
        if (! $token) {
            abort(404);
        }
        if ((int) $token->id === (int) $user->currentAccessToken()?->id) {
            return response()->json(['message' => 'You cannot revoke this session.'], 422);
        }

        $token->delete();
        Auditor::record('session_revoked', 'security', $user, 'User Session', 'Session revoked');

        return response()->json([
            'message' => 'Session revoked',
            'data' => $this->payload($user->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))->delete();
        Auditor::record('session_revoked', 'security', $user, 'User Session', 'Other sessions signed out');

        return response()->json([
            'message' => 'Other sessions signed out',
            'data' => $this->payload($user->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function updateAudit(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $firm = $this->firm($request);
        $data = $request->validate([
            'audit_retention' => ['required', Rule::in(['1y', '3y', '5y', '7y', 'indefinite'])],
        ]);

        $firm->update(['audit_retention' => $data['audit_retention']]);

        return response()->json([
            'message' => 'Audit retention updated',
            'data' => $this->payload($request->user()->fresh(['firm', 'role.permissions'])),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeManage($request);
        $firm = $this->firm($request);
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'firm' => $firm->only(['name', 'email', 'phone', 'city', 'country', 'registration_number']),
            'clients' => Client::query()->orderBy('name')->get(['name', 'type', 'status', 'email']),
            'cases' => LegalCase::query()->with('caseStatus')->orderBy('title')->get()->map(fn (LegalCase $case) => [
                'case_number' => $case->case_number,
                'title' => $case->title,
                'status' => $case->caseStatus?->name,
            ]),
            'documents' => Document::query()->where('is_folder', false)->orderBy('name')->get(['name', 'kind', 'visibility']),
        ];

        $filename = Str::slug($firm->name).'-export-'.now()->toDateString().'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function deactivateWorkspace(Request $request): JsonResponse
    {
        return $this->closeWorkspace($request, 'inactive', 'Workspace deactivated.');
    }

    public function deleteWorkspace(Request $request): JsonResponse
    {
        return $this->closeWorkspace($request, 'deleted', 'Workspace deleted.');
    }

    private function closeWorkspace(Request $request, string $status, string $message): JsonResponse
    {
        $this->authorizeManage($request);
        $firm = $this->firm($request);
        $firm->update([
            'status' => $status,
            'deactivated_at' => now(),
        ]);

        $ids = User::query()->where('firm_id', $firm->id)->pluck('id');
        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $ids)
            ->delete();

        Auditor::record(
            'update',
            'security',
            $firm,
            $firm->name,
            $status === 'deleted' ? 'Workspace marked deleted' : 'Workspace deactivated',
        );

        return response()->json(['message' => $message]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        $user->loadMissing(['firm', 'role.permissions']);
        $firm = $user->firm;
        $prefs = $user->preferences ?? [];
        $notify = $user->notification_preferences ?? [];
        $currentId = $user->currentAccessToken()?->id;

        return [
            'can_manage' => $this->canManage($user),
            'profile' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'job_role' => $user->job_role,
                'name' => $user->name,
                'initials' => $user->initials,
                'password_changed_at' => $prefs['password_changed_at'] ?? null,
            ],
            'firm' => $firm ? [
                'name' => $firm->name,
                'email' => $firm->email,
                'phone' => $firm->phone,
                'website' => $firm->website,
                'address' => $firm->address,
                'city' => $firm->city,
                'country' => $firm->country,
                'registration_number' => $firm->registration_number,
                'initials' => $this->firmInitials($firm->name),
            ] : null,
            'roles' => $this->roles($user),
            'documents' => array_merge($this->defaultDocumentSettings(), $firm?->document_settings ?? []),
            'cases' => [
                'case_number_format' => $firm?->case_number_format ?: '{TYPE}-{YEAR}-{NUMBER}',
                'statuses' => CaseStatus::query()->orderBy('sort_order')->get()
                    ->map(fn (CaseStatus $status) => [
                        'id' => $status->slug,
                        'name' => $status->name,
                        'color' => $status->color,
                        'locked' => (bool) $status->is_archived,
                    ])->values(),
                'types' => CaseType::query()->orderBy('sort_order')->get(['id', 'name'])
                    ->map(fn (CaseType $type) => [
                        'id' => (string) $type->id,
                        'name' => $type->name,
                    ])->values(),
            ],
            'notifications' => [
                'flags' => $notify['flags'] ?? [],
                'hearing_reminder' => $notify['hearing_reminder'] ?? '1d',
                'task_reminder' => $notify['task_reminder'] ?? '1d',
            ],
            'appearance' => [
                'theme' => $prefs['theme'] ?? 'light',
                'density' => $prefs['density'] ?? 'comfortable',
                'sidebar' => $prefs['sidebar'] ?? 'expanded',
                'date_format' => $prefs['date_format'] ?? 'ddmmyyyy',
                'time_format' => $prefs['time_format'] ?? '12h',
            ],
            'security' => [
                'two_factor' => (bool) $user->two_factor_confirmed_at,
                'alerts' => $prefs['login_alerts'] ?? ['device' => true, 'failed' => true, 'changes' => true],
                'password_changed_at' => $prefs['password_changed_at'] ?? null,
            ],
            'sessions' => $user->tokens()->latest('id')->get()->map(function (PersonalAccessToken $token) use ($currentId) {
                $device = Auditor::device($token->user_agent);
                [$browser, $os] = array_pad(explode(' / ', $device, 2), 2, 'Unknown');

                return [
                    'id' => (string) $token->id,
                    'browser' => $browser,
                    'operating_system' => $os,
                    'location' => null,
                    'ip_address' => $token->ip_address ?: '—',
                    'last_active' => $token->last_used_at?->toIso8601String() ?: $token->created_at?->toIso8601String(),
                    'current' => (int) $token->id === (int) $currentId,
                ];
            })->values(),
            'data_audit' => [
                'audit_retention' => $firm?->audit_retention ?: '7y',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roles(User $user): array
    {
        if (! $user->firm_id) {
            return [];
        }

        $allSlugs = Permission::query()->orderBy('sort_order')->pluck('slug');

        return Role::query()
            ->where('firm_id', $user->firm_id)
            ->with('permissions')
            ->withCount('users')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Role $role) use ($allSlugs) {
                $enabled = $role->permissions->pluck('slug');

                return [
                    'id' => $role->slug,
                    'title' => $role->title,
                    'description' => $role->description,
                    'summary' => $this->roleSummary($role->slug),
                    'members' => (int) $role->users_count,
                    'permissions' => $allSlugs->mapWithKeys(fn (string $slug) => [$slug => $enabled->contains($slug)])->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function roleSummary(string $slug): string
    {
        return match ($slug) {
            'administrator' => 'Full access to cases, clients, documents, tasks, team and settings.',
            'managing_partner' => 'Full access to cases, clients, documents, tasks and team.',
            'partner' => 'Manage cases, clients, documents and assigned team members.',
            'lawyer' => 'Work on assigned cases, documents, clients and tasks.',
            'paralegal' => 'Support assigned cases with documents and tasks. No archive or delete.',
            'support' => 'View records and complete administrative tasks with limited create access.',
            'read_only' => 'View permitted cases, clients and documents. No editing.',
            default => 'Custom access role.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultDocumentSettings(): array
    {
        return [
            'default_visibility' => 'private',
            'folder_visibility' => 'private',
            'allow_create_folders' => true,
            'allow_sharing' => true,
            'allow_downloads' => true,
            'organize_by_client' => true,
            'create_default_folders' => true,
            'max_upload_mb' => 100,
            'allowed_types' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX', 'JPG', 'PNG'],
            'default_folders' => ['Contracts', 'Court Documents', 'Evidence', 'Correspondence', 'Invoices', 'Other'],
        ];
    }

    private function firmInitials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'LL';
    }

    private function firm(Request $request): Firm
    {
        $firm = $request->user()?->firm;
        if (! $firm) {
            abort(404);
        }

        return $firm;
    }

    private function findStatus(string $value): CaseStatus
    {
        $status = CaseStatus::query()
            ->where(function ($query) use ($value) {
                $query->where('slug', $value)->orWhere('id', $value);
            })
            ->first();

        if (! $status) {
            abort(404);
        }

        return $status;
    }

    private function authorizeManage(Request $request): void
    {
        if (! $this->canManage($request->user())) {
            abort(403, 'You do not have permission to manage firm settings.');
        }
    }

    private function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->is_platform_admin) {
            return true;
        }
        $user->loadMissing('role.permissions');

        return (bool) $user->role?->permissions?->contains('slug', 'settings.manage');
    }
}
