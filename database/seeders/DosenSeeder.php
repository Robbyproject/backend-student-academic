<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DosenSeeder extends Seeder
{
    public function run(): void
    {
        // Update dosen
        DB::table('tb_dosen')
            ->where('nama', 'Madya Prayogie')
            ->update([
                'nama' => 'Satya Pratama Kadranyata',
            ]);

        $this->command->info('data dosen berhasil diperbarui.');

        
    }
}