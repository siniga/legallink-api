<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $mwangi = DB::table('firms')->where('slug', 'mwangi-partners')->first();
        $harbour = DB::table('firms')->where('slug', 'harbour-legal')->first();
        $james = $this->user('james@mwangiandpartners.com');
        $sarah = $this->user('sarah@mwangiandpartners.com');
        $peter = $this->user('peter@mwangiandpartners.com');
        $aisha = $this->user('aisha@harbourlegal.co.tz');
        $david = $this->user('david@harbourlegal.co.tz');

        $abc = $this->client('ABC Holdings Ltd', $mwangi->id);
        $john = $this->client('John Peter', $mwangi->id);
        $greenfield = $this->client('Greenfield Investments', $harbour->id);
        $abcCase = $this->caseNumber('HC-COMM-125-2026', $mwangi->id);
        $johnCase = $this->caseNumber('HC-CRIM-078-2026', $mwangi->id);
        $greenCase = $this->caseNumber('HC-CIV-210-2026', $harbour->id);

        $witness = DB::table('tasks')->insertGetId([
            'firm_id' => $mwangi->id,
            'title' => 'Prepare witness statement',
            'description' => 'Prepare the witness statement for the upcoming commercial hearing and confirm all referenced exhibits are included.',
            'case_id' => $abcCase,
            'client_id' => $abc,
            'assignee_id' => $james->id,
            'created_by' => $sarah->id,
            'due_at' => now()->endOfDay(),
            'priority' => 'high',
            'status' => 'in_progress',
            'reminder_offset' => '1d',
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('task_checklist_items')->insert([
            ['task_id' => $witness, 'text' => 'Review client statement', 'is_done' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['task_id' => $witness, 'text' => 'Review supporting documents', 'is_done' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['task_id' => $witness, 'text' => 'Draft witness statement', 'is_done' => false, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['task_id' => $witness, 'text' => 'Attach referenced exhibits', 'is_done' => false, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['task_id' => $witness, 'text' => 'Final review', 'is_done' => false, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('tasks')->insert([
            [
                'firm_id' => $mwangi->id,
                'title' => 'File reply to affidavit',
                'description' => 'Draft and file the reply to the opposing affidavit in the criminal matter.',
                'case_id' => $johnCase,
                'client_id' => $john,
                'assignee_id' => $sarah->id,
                'created_by' => $james->id,
                'due_at' => now()->subDays(2)->setTime(16, 0),
                'priority' => 'high',
                'status' => 'pending',
                'reminder_offset' => '3h',
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $mwangi->id,
                'title' => 'Review TRA bundle',
                'description' => 'Index the TRA assessment papers before the next mention.',
                'case_id' => $this->caseNumber('TAX-030-006', $mwangi->id),
                'client_id' => $this->client('XYZ Company Ltd', $mwangi->id),
                'assignee_id' => $peter->id,
                'created_by' => $james->id,
                'due_at' => now()->addDays(3)->setTime(17, 0),
                'priority' => 'medium',
                'status' => 'pending',
                'reminder_offset' => '1d',
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $mwangi->id,
                'title' => 'Send engagement letter',
                'description' => 'Internal admin task with no related case.',
                'case_id' => null,
                'client_id' => $abc,
                'assignee_id' => $james->id,
                'created_by' => $james->id,
                'due_at' => now()->addWeek()->setTime(12, 0),
                'priority' => 'low',
                'status' => 'completed',
                'reminder_offset' => null,
                'completed_at' => now()->subDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $harbour->id,
                'title' => 'Request survey plan',
                'description' => 'Obtain the latest survey plan from the client for the land dispute.',
                'case_id' => $greenCase,
                'client_id' => $greenfield,
                'assignee_id' => $david->id,
                'created_by' => $aisha->id,
                'due_at' => now()->addDays(5)->setTime(10, 0),
                'priority' => 'medium',
                'status' => 'in_progress',
                'reminder_offset' => '1d',
                'completed_at' => null,
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
