<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('audit_logs')->delete();

        $mwangi = DB::table('firms')->where('slug', 'mwangi-partners')->first();
        $harbour = DB::table('firms')->where('slug', 'harbour-legal')->first();
        $james = $this->user('james@mwangiandpartners.com');
        $sarah = $this->user('sarah@mwangiandpartners.com');
        $peter = $this->user('peter@mwangiandpartners.com');
        $mary = $this->user('mary@mwangiandpartners.com');
        $brian = $this->user('brian@mwangiandpartners.com');
        $aisha = $this->user('aisha@harbourlegal.co.tz');
        $abcCase = DB::table('cases')->where('case_number', 'HC-COMM-125-2026')->first();
        $johnCase = DB::table('cases')->where('case_number', 'HC-CRIM-078-2026')->first();
        $xyzCase = DB::table('cases')->where('case_number', 'TAX-030-006')->first();
        $abc = DB::table('clients')->where('firm_id', $mwangi->id)->where('name', 'ABC Holdings Ltd')->first();
        $sunrise = DB::table('clients')->where('firm_id', $mwangi->id)->where('name', 'Sunrise Corp')->first();
        $statement = DB::table('documents')->where('name', 'Witness Statement.docx')->first();
        $agreement = DB::table('documents')->where('name', 'Service Agreement.pdf')->first();
        $pleadings = DB::table('documents')->where('name', 'Pleadings Bundle.pdf')->first();
        $evidence = DB::table('documents')->where('name', 'Evidence')->where('is_folder', true)->first();
        $witnessTask = DB::table('tasks')->where('title', 'Prepare witness statement')->first();
        $replyTask = DB::table('tasks')->where('title', 'File reply to affidavit')->first();
        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0.0.0 Safari/537.36';
        $safari = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 Version/17.5 Safari/605.1.15';
        $edge = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Edg/128.0.0.0 Chrome/128.0.0.0 Safari/537.36';
        $android = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/128.0.0.0 Mobile Safari/537.36';

        $rows = [
            $this->row($mwangi->id, $james->id, 'login', 'security', null, null, 'User Session', 'Successful login', null, null, '197.210.1.23', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-james', now()->subHours(2)),
            $this->row($mwangi->id, $sarah->id, 'login', 'security', null, null, 'User Session', 'Successful login', null, null, '197.210.1.34', $safari, 'Dar es Salaam, Tanzania', 'seed-session-sarah', now()->subHours(3)),
            $this->row($mwangi->id, $peter->id, 'new_device_login', 'security', null, null, 'User Session', 'Successful login from a new device', null, null, '197.210.1.87', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-peter', now()->subHours(4)),
            $this->row($mwangi->id, $james->id, 'failed_login', 'security', null, null, 'User Session', 'Failed login — incorrect password', null, null, '41.59.12.88', $android, 'Unknown location', null, now()->subHours(5)),
            $this->row($mwangi->id, $james->id, 'upload', 'documents', 'documents', $agreement?->id, 'Service Agreement.pdf', 'ABC Holdings Ltd', null, json_encode(['value' => 'firm']), '197.210.1.23', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-james', now()->subHours(1)),
            $this->row($mwangi->id, $sarah->id, 'upload', 'documents', 'documents', $statement?->id, 'Witness Statement.docx', 'Restricted visibility', null, json_encode(['value' => 'restricted']), '197.210.1.34', $safari, 'Dar es Salaam, Tanzania', 'seed-session-sarah', now()->subHours(6)),
            $this->row($mwangi->id, $mary->id, 'share', 'documents', 'documents', $evidence?->id, 'Evidence', 'Shared with James Mwangi', null, null, '197.210.1.41', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-mary', now()->subDay()->setTime(16, 36)),
            $this->row($mwangi->id, $brian->id, 'download', 'documents', 'documents', $pleadings?->id, 'Pleadings Bundle.pdf', $abcCase?->title, null, null, '197.210.1.66', $edge, 'Dar es Salaam, Tanzania', 'seed-session-brian', now()->subDay()->setTime(15, 11)),
            $this->row($mwangi->id, $james->id, 'create', 'cases', 'cases', $abcCase?->id, $abcCase?->title, 'New case created', null, json_encode(['value' => 'Hearing Scheduled']), '197.210.1.23', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-james', now()->subDays(5)),
            $this->row($mwangi->id, $sarah->id, 'hearing_change', 'cases', 'cases', $abcCase?->id, $abcCase?->title, 'Hearing rescheduled', json_encode(['value' => now()->addDays(3)->toDateString(), 'label' => 'Previous Hearing']), json_encode(['value' => now()->addWeeks(2)->toDateString(), 'label' => 'New Hearing']), '197.210.1.34', $safari, 'Dar es Salaam, Tanzania', 'seed-session-sarah', now()->subHours(8)),
            $this->row($mwangi->id, $sarah->id, 'status_change', 'cases', 'cases', $johnCase?->id, $johnCase?->title, 'Open → Adjourned', json_encode(['value' => 'Open', 'label' => 'Before']), json_encode(['value' => 'Adjourned', 'label' => 'After']), '197.210.1.34', $safari, 'Dar es Salaam, Tanzania', 'seed-session-sarah', now()->subDay()->setTime(14, 20)),
            $this->row($mwangi->id, $brian->id, 'update', 'cases', 'cases', $xyzCase?->id, $xyzCase?->title, 'Updated case chronology', null, null, '197.210.1.66', $edge, 'Dar es Salaam, Tanzania', 'seed-session-brian', now()->subHours(3)),
            $this->row($mwangi->id, $peter->id, 'create', 'clients', 'clients', $sunrise?->id, $sunrise?->name, 'New client record created', null, null, '197.210.1.87', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-peter', now()->subHours(7)),
            $this->row($mwangi->id, $james->id, 'update', 'clients', 'clients', $abc?->id, $abc?->name, 'Client record updated', json_encode(['value' => '+255 754 000 000', 'label' => 'Previous Phone']), json_encode(['value' => '+255 754 123 456', 'label' => 'New Phone']), '197.210.1.23', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-james', now()->subDay()),
            $this->row($mwangi->id, $james->id, 'create', 'tasks', 'tasks', $witnessTask?->id, $witnessTask?->title, 'Assigned to James Mwangi', null, null, '197.210.1.23', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-james', now()->subHours(9)),
            $this->row($mwangi->id, $sarah->id, 'update', 'tasks', 'tasks', $replyTask?->id, $replyTask?->title, 'Status changed to In Progress', json_encode(['value' => 'Pending', 'label' => 'Before']), json_encode(['value' => 'In Progress', 'label' => 'After']), '197.210.1.34', $safari, 'Dar es Salaam, Tanzania', 'seed-session-sarah', now()->subDay()->setTime(13, 40)),
            $this->row($mwangi->id, $james->id, 'permission_change', 'security', 'users', $peter->id, 'Peter Kilonzo', 'Role changed from Associate to Senior Associate', json_encode(['value' => 'Associate', 'label' => 'Previous Role']), json_encode(['value' => 'Senior Associate', 'label' => 'New Role']), '197.210.1.23', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-james', now()->subDay()->setTime(11, 5)),
            $this->row($mwangi->id, $james->id, 'session_revoked', 'security', 'users', $this->user('daniel@mwangiandpartners.com')->id, 'Daniel Mrema', 'Session revoked after intern deactivation', null, null, '197.210.1.23', $chrome, 'Dar es Salaam, Tanzania', 'seed-session-james', now()->subDays(3)->setTime(16, 5)),
            $this->row($harbour->id, $aisha->id, 'login', 'security', null, null, 'User Session', 'Successful login', null, null, '41.59.10.12', $chrome, 'Arusha, Tanzania', 'seed-session-aisha', now()->subHours(3)),
            $this->row($harbour->id, $aisha->id, 'create', 'clients', 'clients', DB::table('clients')->where('firm_id', $harbour->id)->where('name', 'Greenfield Investments')->value('id'), 'Greenfield Investments', 'New client record created', null, null, '41.59.10.12', $chrome, 'Arusha, Tanzania', 'seed-session-aisha', now()->subDays(2)),
            $this->row($harbour->id, $aisha->id, 'upload', 'documents', 'documents', DB::table('documents')->where('name', 'Title Deed.pdf')->value('id'), 'Title Deed.pdf', 'Greenfield Investments', null, null, '41.59.10.12', $chrome, 'Arusha, Tanzania', 'seed-session-aisha', now()->subDay()),
        ];

        DB::table('audit_logs')->insert($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $firmId,
        int $userId,
        string $action,
        string $module,
        ?string $subjectType,
        ?int $subjectId,
        ?string $resourceName,
        ?string $details,
        ?string $oldValues,
        ?string $newValues,
        string $ip,
        string $agent,
        ?string $location,
        ?string $sessionId,
        mixed $createdAt,
    ): array {
        return [
            'firm_id' => $firmId,
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'resource_name' => $resourceName,
            'details' => $details,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'location' => $location,
            'session_id' => $sessionId,
            'created_at' => $createdAt,
        ];
    }

    private function user(string $email): object
    {
        return DB::table('users')->where('email', $email)->first();
    }
}
