<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirmInvitationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $mwangi = DB::table('firms')->where('slug', 'mwangi-partners')->first();
        $harbour = DB::table('firms')->where('slug', 'harbour-legal')->first();
        $james = DB::table('users')->where('email', 'james@mwangiandpartners.com')->first();
        $aisha = DB::table('users')->where('email', 'aisha@harbourlegal.co.tz')->first();
        $mwangiLawyer = DB::table('roles')->where('firm_id', $mwangi->id)->where('slug', 'lawyer')->value('id');
        $harbourParalegal = DB::table('roles')->where('firm_id', $harbour->id)->where('slug', 'paralegal')->value('id');

        DB::table('firm_invitations')->insert([
            [
                'firm_id' => $mwangi->id,
                'role_id' => $mwangiLawyer,
                'email' => 'invitee@mwangiandpartners.com',
                'token' => Str::random(40),
                'job_role' => 'associate',
                'invited_by' => $james->id,
                'accepted_at' => null,
                'expires_at' => now()->addDays(7),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'firm_id' => $harbour->id,
                'role_id' => $harbourParalegal,
                'email' => 'invitee@harbourlegal.co.tz',
                'token' => Str::random(40),
                'job_role' => 'paralegal',
                'invited_by' => $aisha->id,
                'accepted_at' => null,
                'expires_at' => now()->addDays(7),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
