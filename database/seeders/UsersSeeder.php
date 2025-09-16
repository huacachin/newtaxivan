<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Faker\Factory as Faker;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_PE');

        // Ajusta este mapa como quieras
        $map = [
            'Guilmer' => 1,
            'Elmer'   => 2,
            'Felix'   => 1,
            'Ivan'    => 1,
            'Jhoseph' => 1,
            'Luis'    => 5,
            'Marko'   => 1,
            'Nancy'   => 3, // vuelven a empezar
            'Licet'   => 1, // vuelven a empezar
        ];

        foreach ($map as $username => $headquarterId) {
            $docType = $faker->randomElement(['DNI', 'CE']);
            $docNumber = $docType === 'DNI'
                ? str_pad((string)$faker->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT)
                : str_pad((string)$faker->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);

            $phone = '9' . str_pad((string)$faker->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT);

            User::updateOrCreate(
                ['username' => $username], // idempotente por username
                [
                    'name'              => $username,
                    'document_type'     => $docType,
                    'document_number'   => $docNumber,
                    'phone'             => $phone,
                    'headquarter_id'    => $headquarterId,
                    'email'             => strtolower($username) . '@taxivan.local',
                    'email_verified_at' => $faker->optional(0.6)->dateTimeBetween('-1 year', 'now'),
                    'password'          => Hash::make('12345678'),
                    'remember_token'    => Str::random(10),
                    'status'            => 'active',
                ]
            );
        }
    }
}
