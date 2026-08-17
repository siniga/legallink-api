<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InboxNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $james = DB::table('users')->where('email', 'james@mwangiandpartners.com')->first();
        $sarah = DB::table('users')->where('email', 'sarah@mwangiandpartners.com')->first();
        $aisha = DB::table('users')->where('email', 'aisha@harbourlegal.co.tz')->first();
        $mwangi = DB::table('firms')->where('slug', 'mwangi-partners')->first();
        $harbour = DB::table('firms')->where('slug', 'harbour-legal')->first();
        $abcCase = DB::table('cases')->where('firm_id', $mwangi->id)->where('case_number', 'HC-COMM-125-2026')->first();
        $greenCase = DB::table('cases')->where('firm_id', $harbour->id)->where('case_number', 'HC-CIV-210-2026')->first();

        if ($james && $sarah && $abcCase) {
            DB::table('inbox_notifications')->insert([
                [
                    'firm_id' => $mwangi->id,
                    'user_id' => $james->id,
                    'type' => 'hearing_changed',
                    'title' => 'Hearing date changed',
                    'body' => $abcCase->title.' · moved by '.$sarah->name,
                    'href' => '/cases/'.$abcCase->id,
                    'subject_type' => 'cases',
                    'subject_id' => $abcCase->id,
                    'dedupe_key' => 'seed:hearing_changed:'.$abcCase->id,
                    'read_at' => null,
                    'created_at' => $now->copy()->subHours(2),
                    'updated_at' => $now->copy()->subHours(2),
                ],
                [
                    'firm_id' => $mwangi->id,
                    'user_id' => $sarah->id,
                    'type' => 'case_assigned',
                    'title' => 'Case assigned to you',
                    'body' => $abcCase->title,
                    'href' => '/cases/'.$abcCase->id,
                    'subject_type' => 'cases',
                    'subject_id' => $abcCase->id,
                    'dedupe_key' => 'seed:case_assigned:'.$abcCase->id.':'.$sarah->id,
                    'read_at' => $now->copy()->subDay(),
                    'created_at' => $now->copy()->subDay(),
                    'updated_at' => $now->copy()->subDay(),
                ],
            ]);
        }

        if ($aisha && $greenCase) {
            DB::table('inbox_notifications')->insert([
                'firm_id' => $harbour->id,
                'user_id' => $aisha->id,
                'type' => 'task_assigned',
                'title' => 'Task assigned to you',
                'body' => 'Obtain survey plan copies',
                'href' => '/tasks',
                'subject_type' => 'tasks',
                'subject_id' => null,
                'dedupe_key' => 'seed:task_assigned:harbour',
                'read_at' => null,
                'created_at' => $now->copy()->subHours(5),
                'updated_at' => $now->copy()->subHours(5),
            ]);
        }
    }
}
