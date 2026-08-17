<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (DB::table('firms')->get() as $firm) {
            $this->seedLookups($firm->id, $now);
        }

        $mwangi = DB::table('firms')->where('slug', 'mwangi-partners')->first();
        $harbour = DB::table('firms')->where('slug', 'harbour-legal')->first();

        $james = $this->user('james@mwangiandpartners.com');
        $sarah = $this->user('sarah@mwangiandpartners.com');
        $peter = $this->user('peter@mwangiandpartners.com');
        $aisha = $this->user('aisha@harbourlegal.co.tz');
        $david = $this->user('david@harbourlegal.co.tz');

        $abc = $this->client('ABC Holdings Ltd', $mwangi->id);
        $john = $this->client('John Peter', $mwangi->id);
        $xyz = $this->client('XYZ Company Ltd', $mwangi->id);
        $greenfield = $this->client('Greenfield Investments', $harbour->id);

        $mwangiTypes = DB::table('case_types')->where('firm_id', $mwangi->id)->pluck('id', 'code');
        $mwangiStatuses = DB::table('case_statuses')->where('firm_id', $mwangi->id)->pluck('id', 'slug');
        $harbourTypes = DB::table('case_types')->where('firm_id', $harbour->id)->pluck('id', 'code');
        $harbourStatuses = DB::table('case_statuses')->where('firm_id', $harbour->id)->pluck('id', 'slug');

        $abcCase = $this->insertCase($mwangi->id, $abc, $james->id, [
            'case_number' => 'HC-COMM-125-2026',
            'title' => 'ABC Holdings Ltd vs XYZ Company',
            'description' => 'Commercial dispute over unpaid invoices and alleged breach of a supply agreement.',
            'case_type_id' => $mwangiTypes['COMM'],
            'case_status_id' => $mwangiStatuses['hearing'],
            'claim_status' => 'anadaiwa',
            'court' => 'High Court – Commercial Division',
        ], $now);

        $johnCase = $this->insertCase($mwangi->id, $john, $sarah->id, [
            'case_number' => 'HC-CRIM-078-2026',
            'title' => 'John Peter vs The Republic',
            'description' => 'Criminal defence matter pending mention.',
            'case_type_id' => $mwangiTypes['CRIM'],
            'case_status_id' => $mwangiStatuses['open'],
            'claim_status' => 'adaiwi',
            'court' => 'High Court – Criminal Division',
        ], $now);

        $xyzCase = $this->insertCase($mwangi->id, $xyz, $peter->id, [
            'case_number' => 'TAX-030-006',
            'title' => 'XYZ Ltd vs TRA',
            'description' => 'Tax appeal against TRA assessment.',
            'case_type_id' => $mwangiTypes['TAX'],
            'case_status_id' => $mwangiStatuses['open'],
            'claim_status' => 'anadaiwa',
            'court' => 'Tax Revenue Appeals Tribunal',
        ], $now);

        $closedCase = $this->insertCase($mwangi->id, $abc, $james->id, [
            'case_number' => 'CIV-2024-019',
            'title' => 'ABC Holdings Ltd vs Lake Traders',
            'description' => 'Matter settled and closed.',
            'case_type_id' => $mwangiTypes['CIV'],
            'case_status_id' => $mwangiStatuses['closed'],
            'claim_status' => 'anadaiwa',
            'court' => 'High Court – Civil Division',
        ], $now);

        $greenCase = $this->insertCase($harbour->id, $greenfield, $aisha->id, [
            'case_number' => 'HC-CIV-210-2026',
            'title' => 'Greenfield Investments vs MNO Ltd',
            'description' => 'Land and construction dispute.',
            'case_type_id' => $harbourTypes['LAND'],
            'case_status_id' => $harbourStatuses['pending'],
            'claim_status' => 'anadaiwa',
            'court' => 'High Court – Civil Division',
        ], $now);

        DB::table('case_user')->insert([
            ['case_id' => $abcCase, 'user_id' => $james->id, 'is_lead' => true, 'created_at' => $now, 'updated_at' => $now],
            ['case_id' => $abcCase, 'user_id' => $sarah->id, 'is_lead' => false, 'created_at' => $now, 'updated_at' => $now],
            ['case_id' => $johnCase, 'user_id' => $sarah->id, 'is_lead' => true, 'created_at' => $now, 'updated_at' => $now],
            ['case_id' => $xyzCase, 'user_id' => $peter->id, 'is_lead' => true, 'created_at' => $now, 'updated_at' => $now],
            ['case_id' => $closedCase, 'user_id' => $james->id, 'is_lead' => true, 'created_at' => $now, 'updated_at' => $now],
            ['case_id' => $greenCase, 'user_id' => $aisha->id, 'is_lead' => true, 'created_at' => $now, 'updated_at' => $now],
            ['case_id' => $greenCase, 'user_id' => $david->id, 'is_lead' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function seedLookups(int $firmId, mixed $now): void
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
            DB::table('case_types')->insert([
                'firm_id' => $firmId,
                'name' => $type['name'],
                'code' => $type['code'],
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
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
            DB::table('case_statuses')->insert([
                'firm_id' => $firmId,
                'slug' => $status['slug'],
                'name' => $status['name'],
                'color' => $status['color'],
                'sort_order' => $index,
                'is_closed' => $status['closed'],
                'is_archived' => $status['archived'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertCase(int $firmId, int $clientId, int $createdBy, array $data, mixed $now): int
    {
        return DB::table('cases')->insertGetId(array_merge($data, [
            'firm_id' => $firmId,
            'client_id' => $clientId,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    private function client(string $name, int $firmId): int
    {
        return (int) DB::table('clients')->where('firm_id', $firmId)->where('name', $name)->value('id');
    }

    private function user(string $email): object
    {
        return DB::table('users')->where('email', $email)->first();
    }
}
