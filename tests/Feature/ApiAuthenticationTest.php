<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_requests_return_json_instead_of_redirecting_to_login(): void
    {
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_student_login_returns_the_student_profile_id_separately_from_the_user_id(): void
    {
        $departmentId = DB::table('jurusan')->insertGetId([
            'kode_jurusan' => 'IF',
            'nama_jurusan' => 'Informatika',
            'created_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'email' => 'student@example.test',
            'password' => Hash::make('secret-password'),
            'role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentId = DB::table('mahasiswa')->insertGetId([
            'user_id' => $userId,
            'nim' => 'TEST-001',
            'nama' => 'Student Test',
            'jurusan_id' => $departmentId,
            'angkatan' => 2026,
            'created_at' => now(),
        ]);

        $this->postJson('/api/login', [
            'email' => 'student@example.test',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $userId)
            ->assertJsonPath('user.mahasiswa_id', $studentId)
            ->assertJsonPath('user.mahasiswa.id', $studentId)
            ->assertJsonPath('user.mahasiswa.nim', 'TEST-001');
    }
}
