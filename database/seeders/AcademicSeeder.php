<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            /*
            | 1. JURUSAN
            */

            $jurusan = DB::table('tb_jurusan')->first();

            if (!$jurusan) {
                $jurusanId = (string) Str::uuid();
                DB::table('tb_jurusan')->insert([
                    'id' => $jurusanId,
                    'kode_jurusan' => 'IF',
                    'nama_jurusan' => 'Informatika',
                    'created_at' => now(),
                ]);

                $jurusan = DB::table('tb_jurusan')
                    ->where('id', $jurusanId)
                    ->first();
            }

            /*
            | 2. USER DOSEN
            */

            // user yang sudah memiliki role dosen
            $userDosen = DB::table('tb_users')
                ->where('role', 'dosen')
                ->first();

            //user dummy dosen
            if (!$userDosen) {
                $userDosenId = (string) Str::uuid();
                DB::table('tb_users')->insert([
                    'id' => $userDosenId,
                    'email' => 'MadyaPrayogie@gmail.com',
                    'password' => Hash::make('password123'),
                    'role' => 'dosen',
                ]);

                $userDosen = DB::table('tb_users')
                    ->where('id', $userDosenId)
                    ->first();
            }

            /*
            | 3. DATA DOSEN
            */

            $dosen = DB::table('tb_dosen')
                ->where('user_id', $userDosen->id)
                ->first();

            if (!$dosen) {
                $dosenId = (string) Str::uuid();
                DB::table('tb_dosen')->insert([
                    'id' => $dosenId,
                    'user_id' => $userDosen->id,
                    'nidn' => '0123456789',
                    'nama' => 'Madya Prayogie',
                    'jurusan_id' => $jurusan->id,
                ]);

                $dosen = DB::table('tb_dosen')
                    ->where('id', $dosenId)
                    ->first();
            }

            /*
            | 4. DATA MATA KULIAH
            */

            $matkulId = (string) Str::uuid();

            DB::table('tb_matkul')->insert([
                'id' => $matkulId,
                'kode_matkul' => 'SDLC4',
                'nama_matkul' => 'Software Development Life Cycle',
                'sks' => 3,
                'jurusan_id' => $jurusan->id,
                'created_at' => now(),
            ]);

            /*
            | 5. DATA KELAS
            */

            $kelasId = (string) Str::uuid();

            DB::table('tb_kelas')->insert([
                'id' => $kelasId,
                'matkul_id' => $matkulId,
                'dosen_id' => $dosen->id,
                'nama_kelas' => 'SDLC4 - Computer Lab',
                'tahun_ajaran' => '2026/2027',
                'created_at' => now(),
            ]);

            /*
            | 6. DATA JADWAL
            */

            DB::table('tb_jadwal')->insert([
                'id' => (string) Str::uuid(),
                'kelas_id' => $kelasId,
                'hari' => 'Senin',
                'jam_mulai' => '08:30:00',
                'jam_selesai' => '11:30:00',
                'ruangan' => 'Computer Lab',
                'created_at' => now(),
            ]);

            DB::table('tb_jadwal')->insert([
                'id' => (string) Str::uuid(),
                'kelas_id' => $kelasId,
                'hari' => 'Rabu',
                'jam_mulai' => '13:00:00',
                'jam_selesai' => '15:30:00',
                'ruangan' => 'Ruang Sumbawa',
                'created_at' => now(),
            ]);

            /*
            | 7. DATA TUGAS
            */

            DB::table('tb_tugas')->insert([
                'id' => (string) Str::uuid(),
                'kelas_id' => $kelasId,
                'judul' => 'Tugas Membuat Dashboard React',
                'deskripsi' => 'Membuat dashboard akademik menggunakan React dan TypeScript.',
                'file_attachment_path' => null,
                'deadline' => '2026-09-30 23:59:00',
                'created_at' => now(),
            ]);

            DB::table('tb_tugas')->insert([
                'id' => (string) Str::uuid(),
                'kelas_id' => $kelasId,
                'judul' => 'Tugas Integrasi API Laravel',
                'deskripsi' => 'Menghubungkan frontend React dengan API Laravel.',
                'file_attachment_path' => null,
                'deadline' => '2026-10-05 23:59:00',
                'created_at' => now(),
            ]);
        });

        $this->command->info('Academic dummy data berhasil dibuat.');
    }
}