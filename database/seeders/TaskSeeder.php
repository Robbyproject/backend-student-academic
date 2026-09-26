<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        // Update tugas pertama
        DB::table('tb_tugas')
            ->where('judul', 'Tugas Membuat Dashboard React')
            ->update([
                'judul' => 'Tugas Analisis and Planning SDLC',
                'deskripsi' => 'Membuat analisis dan perencanaan untuk proyek SDLC.',
            ]);

        // Update tugas kedua
        DB::table('tb_tugas')
            ->where('judul', 'Tugas Integrasi API Laravel')
            ->update([
                'judul' => 'Tugas Prototyping',
                'deskripsi' => 'Membuat prototipe untuk proyek SDLC.',
            ]);

        $this->command->info('2 data tugas berhasil diperbarui.');

        
    }
}