<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_read_incomplete_profile_status_and_save_profile_using_authenticated_account(): void
    {
        $studentUserId = $this->createUser('mahasiswa@example.test', 'mahasiswa', 'Nama Mahasiswa');
        $departmentId = $this->createDepartment();
        $this->createProfile($studentUserId, $departmentId, '23123456');

        $this->actingAsUser($studentUserId)
            ->getJson('/api/mahasiswa/profile')
            ->assertOk()
            ->assertJsonPath('profile_status', 'incomplete')
            ->assertJsonPath('profile.nim', '23123456')
            ->assertJsonPath('account.nama', 'Nama Mahasiswa');

        $this->actingAsUser($studentUserId)
            ->putJson('/api/mahasiswa/profile', [
                'no_telepon' => '08123456789',
                'jenis_kelamin' => 'Laki-laki',
            ])
            ->assertOk()
            ->assertJsonPath('profile_status', 'complete')
            ->assertJsonPath('data.nama', 'Nama Mahasiswa')
            ->assertJsonPath('data.nim', '23123456');

        $this->assertDatabaseHas('mahasiswa', [
            'user_id' => $studentUserId,
            'nim' => '23123456',
            'nama' => 'Nama Mahasiswa',
            'jurusan_id' => $departmentId,
            'angkatan' => 2026,
            'no_telepon' => '08123456789',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $this->actingAsUser($studentUserId)
            ->getJson('/api/mahasiswa/profile')
            ->assertOk()
            ->assertJsonPath('profile_status', 'complete')
            ->assertJsonPath('profile.nim', '23123456');
    }

    public function test_student_cannot_choose_another_accounts_profile_or_use_invalid_data(): void
    {
        $studentUserId = $this->createUser('mahasiswa@example.test', 'mahasiswa');
        $otherStudentId = $this->createUser('mahasiswa2@example.test', 'mahasiswa');
        $departmentId = $this->createDepartment();
        $this->createProfile($studentUserId, $departmentId, '23122222');
        $this->createProfile($otherStudentId, $departmentId, '23111111');

        $this->actingAsUser($studentUserId)
            ->putJson('/api/mahasiswa/profile', [
                'user_id' => $otherStudentId,
                'nim' => '23122222',
                'no_telepon' => '08123456789',
                'jenis_kelamin' => 'Laki-laki',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'nim']);

        $this->actingAsUser($studentUserId)
            ->putJson('/api/mahasiswa/profile', [
                'jurusan_id' => $departmentId + 100,
                'angkatan' => 2030,
                'no_telepon' => '08123456789',
                'jenis_kelamin' => 'Laki-laki',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jurusan_id', 'angkatan']);
    }

    public function test_student_cannot_create_an_academic_profile_before_admin_assigns_nim_and_department(): void
    {
        $studentUserId = $this->createUser('mahasiswa@example.test', 'mahasiswa');
        $departmentId = $this->createDepartment();

        $this->actingAsUser($studentUserId)
            ->getJson('/api/mahasiswa/profile')
            ->assertOk()
            ->assertJsonPath('profile_status', 'incomplete')
            ->assertJsonPath('profile', null);

        $this->actingAsUser($studentUserId)
            ->putJson('/api/mahasiswa/profile', [
                'no_telepon' => '08123456789',
                'jenis_kelamin' => 'Laki-laki',
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('mahasiswa', ['user_id' => $studentUserId]);
    }

    public function test_only_authenticated_mahasiswa_can_access_student_profile_endpoints(): void
    {
        $adminUserId = $this->createUser('admin@example.test', 'admin');

        $this->getJson('/api/mahasiswa/profile')->assertUnauthorized();

        $this->actingAsUser($adminUserId)
            ->getJson('/api/mahasiswa/profile')
            ->assertForbidden();
    }

    private function createUser(string $email, string $role, ?string $name = null): int
    {
        return DB::table('users')->insertGetId([
            'nama' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDepartment(): int
    {
        return DB::table('jurusan')->insertGetId([
            'kode_jurusan' => 'IF',
            'nama_jurusan' => 'Informatika',
            'created_at' => now(),
        ]);
    }

    private function createProfile(int $userId, int $departmentId, string $nim): int
    {
        return DB::table('mahasiswa')->insertGetId([
            'user_id' => $userId,
            'nim' => $nim,
            'nama' => 'Nama Mahasiswa',
            'jurusan_id' => $departmentId,
            'angkatan' => 2026,
            'created_at' => now(),
        ]);
    }

    private function actingAsUser(int $userId): static
    {
        return $this->actingAs(\App\Models\User::findOrFail($userId), 'sanctum');
    }
}
