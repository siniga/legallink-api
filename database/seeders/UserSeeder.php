<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $password = Hash::make('password');
        $preferences = json_encode([
            'theme' => 'light',
            'density' => 'comfortable',
            'sidebar' => 'expanded',
            'date_format' => 'ddmmyyyy',
            'time_format' => '12h',
        ]);
        $notifications = json_encode([
            'hearing_reminder' => true,
            'hearing_changed' => true,
            'case_status' => true,
            'case_assigned' => true,
            'task_assigned' => true,
            'task_due' => true,
            'task_overdue' => true,
            'doc_shared' => true,
            'hearing_reminder_offset' => '1d',
            'task_reminder_offset' => '1d',
        ]);

        DB::table('users')->insert([
            'firm_id' => null,
            'role_id' => null,
            'name' => 'Platform Admin',
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'email' => 'platform@legallink.test',
            'phone' => null,
            'email_verified_at' => $now,
            'password' => $password,
            'job_role' => null,
            'status' => 'active',
            'is_platform_admin' => true,
            'preferences' => $preferences,
            'notification_preferences' => $notifications,
            'remember_token' => Str::random(10),
            'joined_at' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $mwangi = DB::table('firms')->where('slug', 'mwangi-partners')->first();
        $harbour = DB::table('firms')->where('slug', 'harbour-legal')->first();
        $mwangiRoles = DB::table('roles')->where('firm_id', $mwangi->id)->pluck('id', 'slug');
        $harbourRoles = DB::table('roles')->where('firm_id', $harbour->id)->pluck('id', 'slug');

        foreach ($this->mwangiUsers() as $user) {
            $this->insertUser($user, $mwangi->id, $mwangiRoles[$user['access_role']], $password, $preferences, $notifications, $now);
        }

        foreach ($this->harbourUsers() as $user) {
            $this->insertUser($user, $harbour->id, $harbourRoles[$user['access_role']], $password, $preferences, $notifications, $now);
        }
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function insertUser(
        array $user,
        int $firmId,
        int $roleId,
        string $password,
        string $preferences,
        string $notifications,
        mixed $now,
    ): void {
        DB::table('users')->insert([
            'firm_id' => $firmId,
            'role_id' => $roleId,
            'name' => $user['first_name'].' '.$user['last_name'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'email_verified_at' => $now,
            'password' => $password,
            'job_role' => $user['job_role'],
            'status' => $user['status'],
            'is_platform_admin' => false,
            'preferences' => $preferences,
            'notification_preferences' => $notifications,
            'last_login_at' => $user['last_login_at'],
            'remember_token' => Str::random(10),
            'joined_at' => $user['joined_at'],
            'deactivated_at' => $user['status'] === 'inactive' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mwangiUsers(): array
    {
        return [
            [
                'first_name' => 'James',
                'last_name' => 'Mwangi',
                'email' => 'james@mwangiandpartners.com',
                'phone' => '+255 754 123 456',
                'job_role' => 'managing_partner',
                'access_role' => 'administrator',
                'status' => 'active',
                'joined_at' => '2024-01-12',
                'last_login_at' => now(),
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah@mwangiandpartners.com',
                'phone' => '+255 713 456 789',
                'job_role' => 'senior_associate',
                'access_role' => 'lawyer',
                'status' => 'active',
                'joined_at' => '2024-03-04',
                'last_login_at' => now()->subMinutes(10),
            ],
            [
                'first_name' => 'Peter',
                'last_name' => 'Kilonzo',
                'email' => 'peter@mwangiandpartners.com',
                'phone' => '+255 755 221 098',
                'job_role' => 'associate',
                'access_role' => 'lawyer',
                'status' => 'active',
                'joined_at' => '2024-06-18',
                'last_login_at' => now()->subMinutes(35),
            ],
            [
                'first_name' => 'Mary',
                'last_name' => 'Wambui',
                'email' => 'mary@mwangiandpartners.com',
                'phone' => '+255 784 332 144',
                'job_role' => 'associate',
                'access_role' => 'lawyer',
                'status' => 'active',
                'joined_at' => '2024-08-02',
                'last_login_at' => now()->subHour(),
            ],
            [
                'first_name' => 'Brian',
                'last_name' => 'Otieno',
                'email' => 'brian@mwangiandpartners.com',
                'phone' => '+255 743 118 402',
                'job_role' => 'paralegal',
                'access_role' => 'paralegal',
                'status' => 'active',
                'joined_at' => '2025-01-21',
                'last_login_at' => now()->subHours(2),
            ],
            [
                'first_name' => 'Grace',
                'last_name' => 'Nyoni',
                'email' => 'grace@mwangiandpartners.com',
                'phone' => '+255 716 446 210',
                'job_role' => 'legal_assistant',
                'access_role' => 'support',
                'status' => 'away',
                'joined_at' => '2024-09-09',
                'last_login_at' => now()->subDay(),
            ],
            [
                'first_name' => 'Daniel',
                'last_name' => 'Mrema',
                'email' => 'daniel@mwangiandpartners.com',
                'phone' => '+255 742 990 223',
                'job_role' => 'intern',
                'access_role' => 'read_only',
                'status' => 'inactive',
                'joined_at' => '2025-02-01',
                'last_login_at' => now()->subWeeks(3),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function harbourUsers(): array
    {
        return [
            [
                'first_name' => 'Aisha',
                'last_name' => 'Hassan',
                'email' => 'aisha@harbourlegal.co.tz',
                'phone' => '+255 754 880 112',
                'job_role' => 'managing_partner',
                'access_role' => 'administrator',
                'status' => 'active',
                'joined_at' => '2025-03-01',
                'last_login_at' => now()->subHour(),
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Kimaro',
                'email' => 'david@harbourlegal.co.tz',
                'phone' => '+255 713 880 221',
                'job_role' => 'associate',
                'access_role' => 'lawyer',
                'status' => 'active',
                'joined_at' => '2025-04-15',
                'last_login_at' => now()->subHours(4),
            ],
        ];
    }
}
