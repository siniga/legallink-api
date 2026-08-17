<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FirmSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $settings = json_encode([
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
        ]);

        DB::table('firms')->insert([
            [
                'name' => 'Mwangi & Partners Advocates',
                'slug' => 'mwangi-partners',
                'email' => 'info@mwangiandpartners.com',
                'phone' => '+255 22 212 4567',
                'website' => 'www.mwangiandpartners.com',
                'address' => 'Samora Avenue',
                'city' => 'Dar es Salaam',
                'country' => 'Tanzania',
                'registration_number' => 'LAW-FIRM-2024-001',
                'logo_path' => null,
                'case_number_format' => '{TYPE}-{YEAR}-{NUMBER}',
                'document_settings' => $settings,
                'audit_retention' => '7y',
                'status' => 'active',
                'deactivated_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Harbour Legal',
                'slug' => 'harbour-legal',
                'email' => 'hello@harbourlegal.co.tz',
                'phone' => '+255 27 250 1100',
                'website' => 'www.harbourlegal.co.tz',
                'address' => 'India Street',
                'city' => 'Arusha',
                'country' => 'Tanzania',
                'registration_number' => 'LAW-FIRM-2025-014',
                'logo_path' => null,
                'case_number_format' => '{TYPE}-{YEAR}-{NUMBER}',
                'document_settings' => $settings,
                'audit_retention' => '5y',
                'status' => 'active',
                'deactivated_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
