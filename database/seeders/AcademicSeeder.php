<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            /*
            | 1. JURUSAN
            */

            $jurusan = DB::table('jurusan')
                ->where('kode_jurusan', 'IF')
                ->first();

            if (!$jurusan) {
                $jurusanId = DB::table('jurusan')->insertGetId([
                    'kode_jurusan' => 'IF',
                    'nama_jurusan' => 'Informatika',
                    'created_at' => now(),
                ]);

                $jurusan = DB::table('jurusan')
                    ->where('id', $jurusanId)
                    ->first();
            }

            /*
            | 2. USER DOSEN
            */

            $userDosen = DB::table('users')
                ->where('role', 'dosen')
                ->first();

            // User dummy dosen
            if (!$userDosen) {
                $userDosenId = DB::table('users')->insertGetId([
                    'email' => 'Satya@gmail.com',
                    'password' => Hash::make('password123'),
                    'role' => 'dosen',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $userDosen = DB::table('users')
                    ->where('id', $userDosenId)
                    ->first();
            }

            /*
            | 3. DATA DOSEN
            */

            $dosen = DB::table('dosen')
                ->where('user_id', $userDosen->id)
                ->first();

            if (!$dosen) {
                $dosenId = DB::table('dosen')->insertGetId([
                    'user_id' => $userDosen->id,
                    'nidn' => '0123456789',
                    'nama' => 'Satya Pratama',
                    'jurusan_id' => $jurusan->id,
                    'created_at' => now(),
                ]);

                $dosen = DB::table('dosen')
                    ->where('id', $dosenId)
                    ->first();
            }

            /*
            | 4. DATA MATA KULIAH
            */

            $matkulId = DB::table('matkul')->insertGetId([
                'kode_matkul' => 'SDLC4',
                'nama_matkul' => 'Software Development Life Cycle',
                'sks' => 3,
                'jurusan_id' => $jurusan->id,
                'created_at' => now(),
            ]);

            /*
            | 5. DATA KELAS
            */

            $kelasId = DB::table('kelas')->insertGetId([
                'matkul_id' => $matkulId,
                'dosen_id' => $dosen->id,
                'nama_kelas' => 'SDLC4 - Computer Lab',
                'tahun_ajaran' => '2026/2027',
                'created_at' => now(),
            ]);

            /*
            | 6. DATA JADWAL
            */

            DB::table('jadwal')->insert([
                'kelas_id' => $kelasId,
                'hari' => 'Senin',
                'jam_mulai' => '08:30:00',
                'jam_selesai' => '11:30:00',
                'ruangan' => 'Computer Lab',
                'created_at' => now(),
            ]);

            /*
            | 7. DATA TUGAS
            */

            DB::table('tugas')->insert([
                'kelas_id' => $kelasId,
                'judul' => 'Tugas Analisis and Planning SDLC',
                'deskripsi' => 'Membuat analisis dan perencanaan untuk proyek SDLC.',
                'file_attachment_path' => null,
                'deadline' => '2026-09-30 23:59:00',
                'created_at' => now(),
            ]);
        });

        $this->command->info('Academic dummy data berhasil dibuat.');
    }
}