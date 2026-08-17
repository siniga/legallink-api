<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class Auditor
{
    public static function record(
        string $action,
        string $module,
        ?Model $subject = null,
        ?string $resourceName = null,
        ?string $details = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $oldLabel = null,
        ?string $newLabel = null,
        ?User $actor = null,
        ?int $firmId = null,
        ?string $sessionId = null,
        ?string $location = null,
    ): void {
        try {
            $request = request();
            $actor = $actor ?? $request?->user();
            $token = $actor?->currentAccessToken();

            AuditLog::query()->create([
                'firm_id' => $firmId ?? $actor?->firm_id,
                'user_id' => $actor?->id,
                'action' => $action,
                'module' => $module,
                'subject_type' => self::subjectType($subject),
                'subject_id' => $subject?->getKey(),
                'resource_name' => $resourceName,
                'details' => $details,
                'old_values' => self::pack($oldValue, $oldLabel),
                'new_values' => self::pack($newValue, $newLabel),
                'ip_address' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255) ?: null,
                'location' => $location,
                'session_id' => $sessionId ?? (is_object($token) && isset($token->id) ? (string) $token->id : null),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function device(?string $userAgent): string
    {
        $agent = (string) $userAgent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Windows',
        };

        return $browser.' / '.$os;
    }

    public static function isNewDevice(User $user, ?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        return ! AuditLog::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereIn('action', ['login', 'new_device_login'])
            ->where('user_agent', $userAgent)
            ->exists();
    }

    /**
     * @return array{value: mixed, label?: string}|null
     */
    private static function pack(mixed $value, ?string $label): ?array
    {
        if ($value === null && $label === null) {
            return null;
        }

        $payload = ['value' => $value];
        if ($label) {
            $payload['label'] = $label;
        }

        return $payload;
    }

    private static function subjectType(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Client => 'clients',
            $subject instanceof LegalCase => 'cases',
            $subject instanceof Document => 'documents',
            $subject instanceof Task => 'tasks',
            $subject instanceof User => 'users',
            $subject instanceof CalendarEvent => 'calendar_events',
            $subject instanceof Model => $subject->getTable(),
            default => null,
        };
    }
}
