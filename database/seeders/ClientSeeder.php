<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
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

        $abc = $this->insertClient($mwangi->id, $james->id, [
            'type' => 'company',
            'status' => 'active',
            'name' => 'ABC Holdings Ltd',
            'email' => 'alex@abcholdings.co.tz',
            'phone' => '+255 754 123 456',
            'address' => 'Dar es Salaam, Tanzania',
            'registration_number' => 'TZ-REG-12345',
            'industry' => 'Holdings / Commercial',
            'tin' => 'TZ-TIN-88921',
            'notes' => 'Key commercial client. Prefers monthly written updates.',
        ], $now);

        $john = $this->insertClient($mwangi->id, $sarah->id, [
            'type' => 'individual',
            'status' => 'active',
            'name' => 'John Peter',
            'first_name' => 'John',
            'last_name' => 'Peter',
            'email' => 'john.peter@email.com',
            'phone' => '+255 713 456 789',
            'address' => 'Arusha, Tanzania',
            'id_number' => 'T-1988-00421',
            'occupation' => 'Business owner',
        ], $now);

        $xyz = $this->insertClient($mwangi->id, $peter->id, [
            'type' => 'company',
            'status' => 'active',
            'name' => 'XYZ Company Ltd',
            'email' => 'legal@xyz.co.tz',
            'phone' => '+255 755 987 210',
            'address' => 'Dar es Salaam, Tanzania',
            'registration_number' => 'TZ-REG-77821',
            'industry' => 'Import / Export',
            'tin' => 'TZ-TIN-44120',
        ], $now);

        $this->insertClient($mwangi->id, $james->id, [
            'type' => 'company',
            'status' => 'archived',
            'name' => 'Sunrise Corp',
            'email' => 'info@sunrisecorp.co.tz',
            'phone' => '+255 22 211 0099',
            'address' => 'Mwanza, Tanzania',
            'registration_number' => 'TZ-REG-33001',
            'industry' => 'Energy',
            'archived_at' => $now,
        ], $now);

        $greenfield = $this->insertClient($harbour->id, $aisha->id, [
            'type' => 'company',
            'status' => 'active',
            'name' => 'Greenfield Investments',
            'email' => 'ops@greenfield.co.tz',
            'phone' => '+255 27 250 4400',
            'address' => 'Arusha, Tanzania',
            'registration_number' => 'TZ-REG-55110',
            'industry' => 'Real Estate',
            'tin' => 'TZ-TIN-22018',
        ], $now);

        DB::table('client_contacts')->insert([
            [
                'client_id' => $abc,
                'name' => 'Alex Smith',
                'title' => 'General Counsel',
                'phone' => '+255 754 123 456',
                'email' => 'alex@abcholdings.co.tz',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'client_id' => $abc,
                'name' => 'Fatma Ally',
                'title' => 'Finance Manager',
                'phone' => '+255 754 123 457',
                'email' => 'fatma@abcholdings.co.tz',
                'is_primary' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'client_id' => $john,
                'name' => 'John Peter',
                'title' => 'Client',
                'phone' => '+255 713 456 789',
                'email' => 'john.peter@email.com',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'client_id' => $greenfield,
                'name' => 'Neema Pallangyo',
                'title' => 'Managing Director',
                'phone' => '+255 27 250 4400',
                'email' => 'neema@greenfield.co.tz',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('client_user')->insert([
            ['client_id' => $abc, 'user_id' => $james->id, 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $abc, 'user_id' => $sarah->id, 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $john, 'user_id' => $sarah->id, 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $xyz, 'user_id' => $peter->id, 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $greenfield, 'user_id' => $aisha->id, 'created_at' => $now, 'updated_at' => $now],
            ['client_id' => $greenfield, 'user_id' => $david->id, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('client_notes')->insert([
            [
                'client_id' => $abc,
                'user_id' => $james->id,
                'body' => 'Board wants a hearing strategy memo before 20 May.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'client_id' => $john,
                'user_id' => $sarah->id,
                'body' => 'Client prefers Swahili for in-person meetings.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertClient(int $firmId, int $createdBy, array $data, mixed $now): int
    {
        return DB::table('clients')->insertGetId(array_merge([
            'firm_id' => $firmId,
            'first_name' => null,
            'last_name' => null,
            'id_number' => null,
            'occupation' => null,
            'registration_number' => null,
            'industry' => null,
            'tin' => null,
            'notes' => null,
            'archived_at' => null,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ], $data));
    }

    private function user(string $email): object
    {
        return DB::table('users')->where('email', $email)->first();
    }
}
