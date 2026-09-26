<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Update user
        DB::table('tb_users')
            ->where('email', 'MadyaPrayogie@gmail.com')
            ->update([
                'email' => 'satya.pratama@university.edu',
            ]);

        $this->command->info('data user berhasil diperbarui.');

    }
}