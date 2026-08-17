<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $sort = 0;
        $rows = [];

        foreach ($this->catalog() as $group => $flags) {
            foreach ($flags as $slug => $label) {
                $rows[] = [
                    'group' => $group,
                    'slug' => "{$group}.{$slug}",
                    'label' => $label,
                    'sort_order' => $sort++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('permissions')->insert($rows);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function catalog(): array
    {
        return [
            'cases' => [
                'view' => 'View cases',
                'create' => 'Create cases',
                'edit' => 'Edit cases',
                'archive' => 'Archive cases',
            ],
            'clients' => [
                'view' => 'View clients',
                'create' => 'Create clients',
                'edit' => 'Edit clients',
                'archive' => 'Archive clients',
            ],
            'documents' => [
                'view' => 'View documents',
                'upload' => 'Upload documents',
                'share' => 'Share documents',
                'delete' => 'Delete documents',
            ],
            'tasks' => [
                'view' => 'View tasks',
                'create' => 'Create tasks',
                'assign' => 'Assign tasks',
                'complete' => 'Complete tasks',
            ],
            'team' => [
                'view' => 'View team',
                'manage' => 'Manage team members',
            ],
            'activity' => [
                'view' => 'View activity logs',
            ],
            'settings' => [
                'manage' => 'Manage firm settings',
            ],
        ];
    }
}
