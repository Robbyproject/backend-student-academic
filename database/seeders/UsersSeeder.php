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
            ->where('email', 'satya.pratama@university.edu')
            ->update([
                'password' => 'Satya123', // (Ini password tidak bisa)
            ]);

        $this->command->info('data user berhasil diperbarui.');

    }
}