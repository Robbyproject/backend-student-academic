<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $kelas = DB::table('tb_kelas')
            ->where('nama_kelas', 'SDLC4 - Computer Lab')
            ->first();

        if (!$kelas) {
            throw new \Exception(
                'Kelas SDLC4 - Computer Lab tidak ditemukan.'
            );
        }

        DB::table('tb_jadwal')
            ->where('kelas_id', $kelas->id)
            ->where('hari', 'Rabu')
            ->delete();

        $this->command->info(
            'Jadwal Rabu untuk SDLC4 berhasil dihapus.'
        );
    }
}