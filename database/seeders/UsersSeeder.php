<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        /*
        | 1. CARI / BUAT JURUSAN IF
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
        | 2. BUAT USER ARYA
        */

        $userMahasiswa = DB::table('users')
            ->where('email', 'Arya@gmail.com')
            ->first();

        if (!$userMahasiswa) {
            $userMahasiswaId = DB::table('users')->insertGetId([
                'email' => 'Arya@gmail.com',
                'password' => Hash::make('password123'),
                'role' => 'mahasiswa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userMahasiswa = DB::table('users')
                ->where('id', $userMahasiswaId)
                ->first();
        }

        /*
        | 3. BUAT DATA MAHASISWA ARYA
        */

        $mahasiswa = DB::table('mahasiswa')
            ->where('user_id', $userMahasiswa->id)
            ->first();

        if (!$mahasiswa) {
            DB::table('mahasiswa')->insert([
                'user_id' => $userMahasiswa->id,
                'nim' => '25110300031',
                'nama' => 'Arya Riza Pratama',
                'jurusan_id' => $jurusan->id,
                'angkatan' => 2025,
                'created_at' => now(),
            ]);
        }

        $this->command->info(
            'Mahasiswa berhasil dibuat dan masuk jurusan Informatika.'
        );
    }
}