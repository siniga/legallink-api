<?php

namespace App\Http\Resources;

use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $old = is_array($this->old_values) ? $this->old_values : [];
        $new = is_array($this->new_values) ? $this->new_values : [];
        $action = (string) $this->action;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role?->title ?: $this->jobRoleLabel($this->user->job_role),
                'initials' => $this->user->initials,
            ] : null),
            'action' => $action,
            'module' => $this->module,
            'resource_name' => $this->resource_name ?: '—',
            'details' => $this->details,
            'old_value' => $this->scalar($old),
            'new_value' => $this->scalar($new),
            'old_label' => $old['label'] ?? null,
            'new_label' => $new['label'] ?? null,
            'timestamp' => optional($this->created_at)?->toIso8601String(),
            'ip_address' => $this->ip_address ?: '—',
            'device' => Auditor::device($this->user_agent),
            'location' => $this->location,
            'session_id' => $this->session_id,
            'session_status' => $this->sessionStatus($action),
            'severity' => $this->severity($action),
        ];
    }

    private function scalar(array $values): ?string
    {
        if (array_key_exists('value', $values) && $values['value'] !== null && $values['value'] !== '') {
            return is_scalar($values['value']) ? (string) $values['value'] : json_encode($values['value']);
        }

        foreach ($values as $key => $value) {
            if ($key === 'label' || $value === null || is_array($value)) {
                continue;
            }

            return (string) $value;
        }

        return null;
    }

    private function sessionStatus(string $action): ?string
    {
        return match ($action) {
            'login', 'new_device_login' => 'Active',
            'failed_login' => 'Blocked',
            'session_revoked' => 'Revoked',
            default => null,
        };
    }

    private function severity(string $action): string
    {
        return in_array($action, [
            'failed_login',
            'new_device_login',
            'permission_change',
            'password_change',
            'session_revoked',
            'delete',
        ], true) ? 'attention' : 'normal';
    }

    private function jobRoleLabel(?string $role): string
    {
        return match ($role) {
            'managing_partner' => 'Managing Partner',
            'partner' => 'Partner',
            'senior_associate' => 'Senior Associate',
            'associate' => 'Associate',
            'paralegal' => 'Paralegal',
            'legal_assistant' => 'Legal Assistant',
            'finance_admin' => 'Finance / Admin',
            'intern' => 'Intern',
            default => $role ? str_replace('_', ' ', $role) : 'Team member',
        };
    }
}
