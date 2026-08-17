<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CalendarEventSeeder extends Seeder
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

        DB::table('calendar_events')->insert([
            [
                'firm_id' => $mwangi->id,
                'title' => 'ABC Holdings Ltd vs XYZ Company',
                'type' => 'hearing',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(3)->setTime(10, 0),
                'ends_at' => now()->addDays(3)->setTime(12, 0),
                'all_day' => false,
                'case_id' => $abcCase,
                'client_id' => $abc,
                'assigned_user_id' => $james->id,
                'location' => 'High Court – Commercial Division',
                'purpose' => 'Main hearing',
                'notes' => 'Bring exhibit bundle.',
                'previous_starts_at' => null,
                'reminder_offset' => '1d',
                'created_by' => $james->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $mwangi->id,
                'title' => 'John Peter vs The Republic',
                'type' => 'court_mention',
                'status' => 'scheduled',
                'starts_at' => now()->addDay()->setTime(9, 0),
                'ends_at' => now()->addDay()->setTime(9, 30),
                'all_day' => false,
                'case_id' => $johnCase,
                'client_id' => $john,
                'assigned_user_id' => $sarah->id,
                'location' => 'High Court – Criminal Division',
                'purpose' => 'Mention',
                'notes' => null,
                'previous_starts_at' => now()->subWeeks(2)->setTime(9, 0),
                'reminder_offset' => '3h',
                'created_by' => $sarah->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $mwangi->id,
                'title' => 'Client Meeting',
                'type' => 'meeting',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(2)->setTime(14, 0),
                'ends_at' => now()->addDays(2)->setTime(15, 0),
                'all_day' => false,
                'case_id' => $abcCase,
                'client_id' => $abc,
                'assigned_user_id' => $james->id,
                'location' => 'Conference Room',
                'purpose' => null,
                'notes' => 'Pre-hearing briefing.',
                'previous_starts_at' => null,
                'reminder_offset' => '1h',
                'created_by' => $james->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $mwangi->id,
                'title' => 'File reply deadline',
                'type' => 'deadline',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(4)->startOfDay(),
                'ends_at' => null,
                'all_day' => true,
                'case_id' => $johnCase,
                'client_id' => $john,
                'assigned_user_id' => $sarah->id,
                'location' => null,
                'purpose' => null,
                'notes' => null,
                'previous_starts_at' => null,
                'reminder_offset' => '1d',
                'created_by' => $sarah->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $harbour->id,
                'title' => 'Greenfield Investments vs MNO Ltd',
                'type' => 'court_mention',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(6)->setTime(9, 30),
                'ends_at' => now()->addDays(6)->setTime(10, 0),
                'all_day' => false,
                'case_id' => $greenCase,
                'client_id' => $greenfield,
                'assigned_user_id' => $aisha->id,
                'location' => 'High Court – Civil Division',
                'purpose' => 'Mention',
                'notes' => null,
                'previous_starts_at' => null,
                'reminder_offset' => '1d',
                'created_by' => $aisha->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
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
