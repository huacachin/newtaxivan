<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HeadquartersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $headquarters = [
            'Huaycan',
            'La victoria',
            'Sta Anita',
            'Sta.Clara',
            'lima',
            'Huachipa',
            'H.Gamarra',
        ];

        foreach ($headquarters as $hq) {
            DB::table('headquarters')->insert([
                'name'   => $hq,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
