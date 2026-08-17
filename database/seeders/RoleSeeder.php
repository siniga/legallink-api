<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $allSlugs = $permissionIds->keys()->all();

        foreach (DB::table('firms')->get() as $firm) {
            foreach ($this->roles() as $index => $role) {
                $roleId = DB::table('roles')->insertGetId([
                    'firm_id' => $firm->id,
                    'slug' => $role['slug'],
                    'title' => $role['title'],
                    'description' => $role['description'],
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $rows = [];
                foreach ($this->permissionSlugsFor($role['slug'], $allSlugs) as $slug) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permissionIds[$slug],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('role_permission')->insert($rows);
            }
        }
    }

    /**
     * @return list<array{slug: string, title: string, description: string}>
     */
    private function roles(): array
    {
        return [
            ['slug' => 'administrator', 'title' => 'Administrator', 'description' => 'Full system access'],
            ['slug' => 'managing_partner', 'title' => 'Managing Partner', 'description' => 'Firm-wide legal and management access'],
            ['slug' => 'partner', 'title' => 'Partner', 'description' => 'Case, client and team management'],
            ['slug' => 'lawyer', 'title' => 'Lawyer', 'description' => 'Cases, documents, clients and assigned work'],
            ['slug' => 'paralegal', 'title' => 'Paralegal', 'description' => 'Assigned cases, documents and tasks'],
            ['slug' => 'support', 'title' => 'Support Staff', 'description' => 'Limited administrative access'],
            ['slug' => 'read_only', 'title' => 'Read Only', 'description' => 'View permitted records without editing'],
        ];
    }

    /**
     * @param  list<string>  $allSlugs
     * @return list<string>
     */
    private function permissionSlugsFor(string $role, array $allSlugs): array
    {
        if (in_array($role, ['administrator', 'managing_partner'], true)) {
            return $allSlugs;
        }

        if ($role === 'read_only') {
            return array_values(array_filter($allSlugs, fn (string $slug) => str_ends_with($slug, '.view')));
        }

        $denied = ['settings.manage'];

        if ($role !== 'partner') {
            $denied[] = 'team.manage';
        }

        if (in_array($role, ['paralegal', 'support'], true)) {
            $denied[] = 'cases.archive';
            $denied[] = 'clients.archive';
            $denied[] = 'documents.delete';
        }

        if ($role === 'support') {
            $denied[] = 'cases.create';
            $denied[] = 'clients.create';
        }

        return array_values(array_diff($allSlugs, $denied));
    }
}
