<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StudentSeeder extends Seeder
{
    private const EMAIL = 'robby@gmail.com';

    private const NIM = 'TEST-ROBBY-2026';

    private const PASSWORD = 'password123';

    public function run(): void
    {
        DB::transaction(function (): void {
            $user = DB::table('users')->where('email', self::EMAIL)->first();

            if ($user && $user->role !== 'mahasiswa') {
                throw new RuntimeException(
                    'robby@gmail.com already exists with a role other than mahasiswa.'
                );
            }

            if (! $user) {
                $userId = DB::table('users')->insertGetId([
                    'email' => self::EMAIL,
                    'password' => Hash::make(self::PASSWORD),
                    'role' => 'mahasiswa',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $userId = $user->id;
            }

            if (DB::table('mahasiswa')->where('user_id', $userId)->exists()) {
                $this->command?->info('Akun dan profil mahasiswa Robby sudah tersedia.');

                return;
            }

            $department = DB::table('jurusan')
                ->orderByRaw("CASE WHEN kode_jurusan = 'IF' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->first();

            if (! $department) {
                $departmentId = DB::table('jurusan')->insertGetId([
                    'kode_jurusan' => 'IF',
                    'nama_jurusan' => 'Informatika',
                    'created_at' => now(),
                ]);

                $department = DB::table('jurusan')->where('id', $departmentId)->first();
            }

            if (DB::table('mahasiswa')->where('nim', self::NIM)->exists()) {
                throw new RuntimeException(
                    'The test NIM is already assigned to another student; update the seeder NIM before retrying.'
                );
            }

            DB::table('mahasiswa')->insert([
                'user_id' => $userId,
                'nim' => self::NIM,
                'nama' => 'Robby',
                'jurusan_id' => $department->id,
                'angkatan' => 2026,
                'created_at' => now(),
            ]);

            $this->command?->info('Akun dan profil mahasiswa Robby berhasil dibuat.');
        });
    }
}
