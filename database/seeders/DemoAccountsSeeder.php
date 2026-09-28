<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'email' => 'admin@demo.test',
                'role' => 'admin',
                'password' => 'admin123',
            ],
            [
                'email' => 'dosen@demo.test',
                'role' => 'dosen',
                'password' => 'dossen123',
            ],
            [
                'email' => 'mahasiswa@demo.test',
                'role' => 'mahasiswa',
                'password' => 'mahasiswa123',
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'role' => $account['role'],
                    'password' => $account['password'],
                ],
            );
        }
    }
}
