<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $mwangi = DB::table('firms')->where('slug', 'mwangi-partners')->first();
        $harbour = DB::table('firms')->where('slug', 'harbour-legal')->first();
        $james = $this->user('james@mwangiandpartners.com');
        $sarah = $this->user('sarah@mwangiandpartners.com');
        $aisha = $this->user('aisha@harbourlegal.co.tz');

        $abc = $this->client('ABC Holdings Ltd', $mwangi->id);
        $john = $this->client('John Peter', $mwangi->id);
        $greenfield = $this->client('Greenfield Investments', $harbour->id);
        $abcCase = $this->caseNumber('HC-COMM-125-2026', $mwangi->id);
        $johnCase = $this->caseNumber('HC-CRIM-078-2026', $mwangi->id);
        $greenCase = $this->caseNumber('HC-CIV-210-2026', $harbour->id);

        $clientsRoot = $this->folder($mwangi->id, null, 'Clients', 'firm', $james->id, $now);
        $casesRoot = $this->folder($mwangi->id, null, 'Cases', 'firm', $james->id, $now);
        $this->folder($mwangi->id, null, 'Firm Documents', 'firm', $james->id, $now);
        $this->folder($mwangi->id, null, 'My Documents', 'private', $james->id, $now);

        $abcFolder = $this->folder($mwangi->id, $clientsRoot, 'ABC Holdings Ltd', 'firm', $james->id, $now, $abc);
        $contracts = $this->folder($mwangi->id, $abcFolder, 'Contracts', 'firm', $james->id, $now, $abc);
        $evidence = $this->folder($mwangi->id, $abcFolder, 'Evidence', 'restricted', $james->id, $now, $abc);
        $johnFolder = $this->folder($mwangi->id, $clientsRoot, 'John Peter', 'restricted', $sarah->id, $now, $john);
        $abcCaseFolder = $this->folder($mwangi->id, $casesRoot, 'ABC Holdings Ltd vs XYZ Company', 'firm', $james->id, $now, $abc, $abcCase);

        $this->file($mwangi->id, $contracts, 'Service Agreement.pdf', 'pdf', 'firm', $james->id, $abc, null, 1_200_000, $now);
        $photo = $this->file($mwangi->id, $evidence, 'Site Photo.jpg', 'image', 'restricted', $james->id, $abc, $abcCase, 2_400_000, $now);
        $statement = $this->file($mwangi->id, $johnFolder, 'Witness Statement.docx', 'word', 'restricted', $sarah->id, $john, $johnCase, 856_000, $now);
        $this->file($mwangi->id, $abcCaseFolder, 'Pleadings Bundle.pdf', 'pdf', 'firm', $james->id, $abc, $abcCase, 3_100_000, $now);

        DB::table('document_access')->insert([
            ['document_id' => $evidence, 'user_id' => $james->id, 'access' => 'editor', 'created_at' => $now, 'updated_at' => $now],
            ['document_id' => $evidence, 'user_id' => $sarah->id, 'access' => 'viewer', 'created_at' => $now, 'updated_at' => $now],
            ['document_id' => $photo, 'user_id' => $james->id, 'access' => 'editor', 'created_at' => $now, 'updated_at' => $now],
            ['document_id' => $photo, 'user_id' => $sarah->id, 'access' => 'viewer', 'created_at' => $now, 'updated_at' => $now],
            ['document_id' => $johnFolder, 'user_id' => $james->id, 'access' => 'viewer', 'created_at' => $now, 'updated_at' => $now],
            ['document_id' => $johnFolder, 'user_id' => $sarah->id, 'access' => 'editor', 'created_at' => $now, 'updated_at' => $now],
            ['document_id' => $statement, 'user_id' => $james->id, 'access' => 'viewer', 'created_at' => $now, 'updated_at' => $now],
            ['document_id' => $statement, 'user_id' => $sarah->id, 'access' => 'editor', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $harbourClients = $this->folder($harbour->id, null, 'Clients', 'firm', $aisha->id, $now);
        $greenFolder = $this->folder($harbour->id, $harbourClients, 'Greenfield Investments', 'firm', $aisha->id, $now, $greenfield);
        $this->file($harbour->id, $greenFolder, 'Title Deed.pdf', 'pdf', 'firm', $aisha->id, $greenfield, $greenCase, 980_000, $now);
    }

    private function folder(
        int $firmId,
        ?int $parentId,
        string $name,
        string $visibility,
        int $ownerId,
        mixed $now,
        ?int $clientId = null,
        ?int $caseId = null,
    ): int {
        return DB::table('documents')->insertGetId([
            'firm_id' => $firmId,
            'parent_id' => $parentId,
            'is_folder' => true,
            'name' => $name,
            'kind' => 'folder',
            'client_id' => $clientId,
            'case_id' => $caseId,
            'owner_id' => $ownerId,
            'visibility' => $visibility,
            'created_by' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function file(
        int $firmId,
        int $parentId,
        string $name,
        string $kind,
        string $visibility,
        int $ownerId,
        ?int $clientId,
        ?int $caseId,
        int $size,
        mixed $now,
    ): int {
        return DB::table('documents')->insertGetId([
            'firm_id' => $firmId,
            'parent_id' => $parentId,
            'is_folder' => false,
            'name' => $name,
            'kind' => $kind,
            'client_id' => $clientId,
            'case_id' => $caseId,
            'owner_id' => $ownerId,
            'visibility' => $visibility,
            'disk' => 'local',
            'path' => "firms/{$firmId}/documents/{$name}",
            'original_name' => $name,
            'mime_type' => match ($kind) {
                'pdf' => 'application/pdf',
                'word' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image' => 'image/jpeg',
                default => 'application/octet-stream',
            },
            'size_bytes' => $size,
            'created_by' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function user(string $email): object
    {
        return DB::table('users')->where('email', $email)->first();
    }

    private function client(string $name, int $firmId): int
    {
        return (int) DB::table('clients')->where('firm_id', $firmId)->where('name', $name)->value('id');
    }

    private function caseNumber(string $number, int $firmId): int
    {
        return (int) DB::table('cases')->where('firm_id', $firmId)->where('case_number', $number)->value('id');
    }
}
