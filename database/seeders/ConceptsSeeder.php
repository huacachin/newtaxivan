<?php

namespace Database\Seeders;

use App\Models\Concept;
use Illuminate\Database\Seeder;

class ConceptsSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = [
            ['id' => 1, 'name' => 'DRACO',  'type' => 'egreso', 'status' => 'active'],
            ['id' => 2, 'name' => 'BASE 1', 'type' => 'egreso', 'status' => 'active'],
            ['id' => 3, 'name' => 'BASE 2', 'type' => 'egreso', 'status' => 'active'],
            ['id' => 4, 'name' => 'BASE 3', 'type' => 'egreso', 'status' => 'active'],
        ];

        foreach ($concepts as $data) {
            Concept::updateOrCreate(
                ['id' => $data['id']],
                [
                    'name'   => $data['name'],
                    'type'   => $data['type'],
                    'status' => $data['status'],
                ]
            );
        }
    }
}
