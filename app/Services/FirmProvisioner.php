<?php

namespace App\Services;

use App\Models\CaseStatus;
use App\Models\CaseType;
use App\Models\Document;
use App\Models\Firm;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

class FirmProvisioner
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFirm(array $attributes): Firm
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $firm = Firm::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'website' => $attributes['website'] ?? null,
            'address' => $attributes['address'] ?? null,
            'city' => $attributes['city'] ?? null,
            'country' => $attributes['country'] ?? 'Tanzania',
            'registration_number' => $attributes['registration_number'] ?? null,
            'case_number_format' => '{TYPE}-{YEAR}-{NUMBER}',
            'document_settings' => $this->documentSettings(),
            'audit_retention' => '7y',
            'status' => 'active',
            'deactivated_at' => null,
        ]);

        $this->seedRoles($firm);
        $this->seedLookups($firm);

        return $firm->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createMember(Firm $firm, array $data): User
    {
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $roleSlug = $data['access_role'] ?? 'administrator';
        $roleId = Role::query()
            ->where('firm_id', $firm->id)
            ->where('slug', $roleSlug)
            ->value('id');

        $member = User::query()->create([
            'firm_id' => $firm->id,
            'role_id' => $roleId,
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($first.' '.$last),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'] ?? 'password',
            'job_role' => $data['job_role'] ?? 'managing_partner',
            'status' => 'active',
            'is_platform_admin' => false,
            'preferences' => $this->defaultPreferences(),
            'notification_preferences' => $this->defaultNotifications(),
            'remember_token' => Str::random(10),
            'joined_at' => now()->toDateString(),
        ]);

        if (User::query()->where('firm_id', $firm->id)->count() === 1) {
            $this->seedRootFolders($firm, $member);
        }

        return $member->fresh(['role', 'firm']);
    }

    private function seedRoles(Firm $firm): void
    {
        $permissionIds = Permission::query()->pluck('id', 'slug');
        $allSlugs = $permissionIds->keys()->all();

        foreach ($this->roles() as $index => $role) {
            $created = Role::query()->create([
                'firm_id' => $firm->id,
                'slug' => $role['slug'],
                'title' => $role['title'],
                'description' => $role['description'],
                'sort_order' => $index,
            ]);

            $slugs = $this->permissionSlugsFor($role['slug'], $allSlugs);
            $created->permissions()->sync(
                collect($slugs)->map(fn (string $slug) => $permissionIds[$slug])->filter()->values()->all()
            );
        }
    }

    private function seedLookups(Firm $firm): void
    {
        $types = [
            ['name' => 'Civil', 'code' => 'CIV'],
            ['name' => 'Criminal', 'code' => 'CRIM'],
            ['name' => 'Commercial', 'code' => 'COMM'],
            ['name' => 'Family', 'code' => 'FAM'],
            ['name' => 'Employment', 'code' => 'EMP'],
            ['name' => 'Land', 'code' => 'LAND'],
            ['name' => 'Tax', 'code' => 'TAX'],
            ['name' => 'Corporate', 'code' => 'CORP'],
            ['name' => 'Other', 'code' => 'OTH'],
        ];

        foreach ($types as $index => $type) {
            CaseType::withoutGlobalScope('firm')->create([
                'firm_id' => $firm->id,
                'name' => $type['name'],
                'code' => $type['code'],
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        $statuses = [
            ['slug' => 'open', 'name' => 'Open', 'color' => 'bg-blue-500', 'closed' => false, 'archived' => false],
            ['slug' => 'pending', 'name' => 'Pending', 'color' => 'bg-amber-500', 'closed' => false, 'archived' => false],
            ['slug' => 'hearing', 'name' => 'Hearing Scheduled', 'color' => 'bg-violet-500', 'closed' => false, 'archived' => false],
            ['slug' => 'adjourned', 'name' => 'Adjourned', 'color' => 'bg-orange-500', 'closed' => false, 'archived' => false],
            ['slug' => 'review', 'name' => 'Under Review', 'color' => 'bg-sky-500', 'closed' => false, 'archived' => false],
            ['slug' => 'closed', 'name' => 'Closed', 'color' => 'bg-emerald-500', 'closed' => true, 'archived' => false],
            ['slug' => 'archived', 'name' => 'Archived', 'color' => 'bg-slate-400', 'closed' => false, 'archived' => true],
        ];

        foreach ($statuses as $index => $status) {
            CaseStatus::withoutGlobalScope('firm')->create([
                'firm_id' => $firm->id,
                'slug' => $status['slug'],
                'name' => $status['name'],
                'color' => $status['color'],
                'sort_order' => $index,
                'is_closed' => $status['closed'],
                'is_archived' => $status['archived'],
            ]);
        }
    }

    private function seedRootFolders(Firm $firm, User $owner): void
    {
        foreach (['Clients', 'Cases', 'Firm Documents'] as $name) {
            Document::withoutGlobalScope('firm')->create([
                'firm_id' => $firm->id,
                'is_folder' => true,
                'name' => $name,
                'kind' => 'folder',
                'owner_id' => $owner->id,
                'visibility' => 'firm',
                'created_by' => $owner->id,
            ]);
        }

        Document::withoutGlobalScope('firm')->create([
            'firm_id' => $firm->id,
            'is_folder' => true,
            'name' => 'My Documents',
            'kind' => 'folder',
            'owner_id' => $owner->id,
            'visibility' => 'private',
            'created_by' => $owner->id,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'firm';
        $slug = $base;
        $suffix = 2;

        while (Firm::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSettings(): array
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

    /**
     * @return array<string, mixed>
     */
    private function defaultPreferences(): array
    {
        return [
            'theme' => 'light',
            'density' => 'comfortable',
            'sidebar' => 'expanded',
            'date_format' => 'ddmmyyyy',
            'time_format' => '12h',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultNotifications(): array
    {
        return [
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
        ];
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
