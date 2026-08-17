<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            FirmSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            ClientSeeder::class,
            CaseSeeder::class,
            DocumentSeeder::class,
            TaskSeeder::class,
            CalendarEventSeeder::class,
            AuditLogSeeder::class,
            InboxNotificationSeeder::class,
            FirmInvitationSeeder::class,
        ]);
    }
}
