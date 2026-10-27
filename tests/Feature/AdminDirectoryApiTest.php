<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_departments_and_filter_lecturers_by_department(): void
    {
        $adminId = $this->createUser('admin@example.test', 'admin');
        $departmentId = $this->createDepartment('IF', 'Informatika');
        $otherDepartmentId = $this->createDepartment('SI', 'Sistem Informasi');

        $this->actingAsUser($adminId)
            ->getJson('/api/admin/jurusan')
            ->assertOk()
            ->assertJsonFragment(['id' => $departmentId, 'kode_jurusan' => 'IF']);

        $lecturerResponse = $this->actingAsUser($adminId)
            ->postJson('/api/admin/dosen', [
                'email' => 'lecturer@kampus.ac.id',
                'password' => 'password-rahasia',
                'nidn' => '1234567890',
                'nama' => 'Dosen Informatika',
                'jurusan_id' => $departmentId,
            ]);
        $lecturerId = $lecturerResponse->json('data.id');
        $lecturerResponse
            ->assertCreated()
            ->assertJsonPath('data.jurusan_id', $departmentId);
        $lecturerUserId = $lecturerResponse->json('data.user_id');
        $this->assertSame('dosen', DB::table('users')->where('id', $lecturerUserId)->value('role'));
        $this->assertTrue(Hash::check('password-rahasia', DB::table('users')->where('id', $lecturerUserId)->value('password')));

        $this->actingAsUser($adminId)
            ->getJson('/api/admin/dosen?jurusan_id='.$departmentId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Dosen Informatika')
            ->assertJsonPath('data.0.nama_jurusan', 'Informatika')
            ->assertJsonPath('data.0.email', 'lecturer@kampus.ac.id');

        $this->actingAsUser($adminId)
            ->getJson('/api/admin/dosen?jurusan_id='.$otherDepartmentId)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAsUser($adminId)
            ->patchJson('/api/admin/dosen/'.$lecturerId, [
                'nama' => 'Nama Dosen Baru',
                'email' => 'updated-lecturer@kampus.ac.id',
                'password' => 'password-baru',
            ])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Nama Dosen Baru')
            ->assertJsonPath('data.email', 'updated-lecturer@kampus.ac.id');
        $this->assertSame('updated-lecturer@kampus.ac.id', DB::table('users')->where('id', $lecturerUserId)->value('email'));
        $this->assertTrue(Hash::check('password-baru', DB::table('users')->where('id', $lecturerUserId)->value('password')));

        $this->actingAsUser($adminId)
            ->patchJson('/api/admin/jurusan/'.$departmentId, ['nama_jurusan' => 'Teknik Informatika'])
            ->assertOk()
            ->assertJsonPath('data.nama_jurusan', 'Teknik Informatika');
    }

    public function test_department_and_lecturer_deletion_is_rejected_when_records_are_in_use(): void
    {
        $adminId = $this->createUser('admin@example.test', 'admin');
        $lecturerUserId = $this->createUser('lecturer@example.test', 'dosen');
        $departmentId = $this->createDepartment('IF', 'Informatika');
        $lecturerId = DB::table('dosen')->insertGetId([
            'user_id' => $lecturerUserId,
            'nidn' => '1234567890',
            'nama' => 'Dosen Informatika',
            'jurusan_id' => $departmentId,
            'created_at' => now(),
        ]);
        $courseId = DB::table('matkul')->insertGetId([
            'kode_matkul' => 'IF101',
            'nama_matkul' => 'Pengantar Informatika',
            'sks' => 3,
            'jurusan_id' => $departmentId,
            'created_at' => now(),
        ]);
        DB::table('kelas')->insert([
            'matkul_id' => $courseId,
            'dosen_id' => $lecturerId,
            'nama_kelas' => 'A',
            'tahun_ajaran' => '2026/2027',
            'created_at' => now(),
        ]);

        $this->actingAsUser($adminId)
            ->deleteJson('/api/admin/dosen/'.$lecturerId)
            ->assertStatus(409);

        $this->actingAsUser($adminId)
            ->deleteJson('/api/admin/jurusan/'.$departmentId)
            ->assertStatus(409);
    }

    public function test_admin_can_create_replace_and_delete_directory_records(): void
    {
        $adminId = $this->createUser('admin@example.test', 'admin');

        $departmentResponse = $this->actingAsUser($adminId)
            ->postJson('/api/admin/jurusan', [
                'kode_jurusan' => 'IF',
                'nama_jurusan' => 'Informatika',
            ]);
        $departmentId = $departmentResponse->json('data.id');
        $departmentResponse->assertCreated();

        $this->actingAsUser($adminId)
            ->putJson('/api/admin/jurusan/'.$departmentId, [
                'kode_jurusan' => 'IF',
                'nama_jurusan' => 'Teknik Informatika',
            ])
            ->assertOk()
            ->assertJsonPath('data.nama_jurusan', 'Teknik Informatika');

        $lecturerResponse = $this->actingAsUser($adminId)
            ->postJson('/api/admin/dosen', [
                'email' => 'lecturer@kampus.ac.id',
                'password' => 'password-rahasia',
                'nidn' => '1234567890',
                'nama' => 'Nama Dosen',
                'jurusan_id' => $departmentId,
            ]);
        $lecturerId = $lecturerResponse->json('data.id');
        $lecturerUserId = $lecturerResponse->json('data.user_id');
        $lecturerResponse->assertCreated();

        $this->actingAsUser($adminId)
            ->putJson('/api/admin/dosen/'.$lecturerId, [
                'email' => 'lecturer@kampus.ac.id',
                'password' => 'password-baru',
                'nidn' => '1234567890',
                'nama' => 'Nama Dosen Diperbarui',
                'jurusan_id' => $departmentId,
            ])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Nama Dosen Diperbarui');

        $this->actingAsUser($adminId)
            ->deleteJson('/api/admin/dosen/'.$lecturerId)
            ->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $lecturerUserId]);

        $this->actingAsUser($adminId)
            ->deleteJson('/api/admin/jurusan/'.$departmentId)
            ->assertOk();
    }

    public function test_admin_can_create_a_mahasiswa_account_and_base_profile_together(): void
    {
        $adminId = $this->createUser('admin@example.test', 'admin');
        $departmentId = $this->createDepartment('IF', 'Informatika');

        $response = $this->actingAsUser($adminId)
            ->postJson('/api/admin/users', [
                'nama' => 'Mahasiswa Test',
                'email' => 'mahasiswa@kampus.ac.id',
                'password' => 'password-rahasia',
                'jurusan_id' => $departmentId,
                'angkatan' => 2025,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Mahasiswa Test')
            ->assertJsonPath('data.role', 'mahasiswa')
            ->assertJsonPath('data.nim', '00000001')
            ->assertJsonPath('data.jurusan_id', $departmentId)
            ->assertJsonPath('data.angkatan', 2025)
            ->assertJsonMissingPath('data.password');

        $userId = $response->json('data.id');
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'nama' => 'Mahasiswa Test',
            'email' => 'mahasiswa@kampus.ac.id',
            'role' => 'mahasiswa',
        ]);
        $this->assertTrue(Hash::check('password-rahasia', DB::table('users')->where('id', $userId)->value('password')));
        $this->assertDatabaseHas('mahasiswa', [
            'id' => $response->json('data.mahasiswa_id'),
            'user_id' => $userId,
            'nim' => '00000001',
            'jurusan_id' => $departmentId,
            'angkatan' => 2025,
        ]);
    }

    public function test_admin_student_creation_rejects_non_campus_email_and_allocates_unique_sequential_nims(): void
    {
        $adminId = $this->createUser('admin@example.test', 'admin');
        $departmentId = $this->createDepartment('IF', 'Informatika');
        $endpoint = '/api/admin/users';

        $this->actingAsUser($adminId)
            ->postJson($endpoint, [
                'nama' => 'Invalid Email',
                'email' => 'student@example.test',
                'password' => 'password-rahasia',
                'jurusan_id' => $departmentId,
                'angkatan' => 2025,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $basePayload = [
            'nama' => 'Mahasiswa Test',
            'password' => 'password-rahasia',
            'jurusan_id' => $departmentId,
            'angkatan' => 2025,
        ];

        $this->actingAsUser($adminId)
            ->postJson($endpoint, [...$basePayload, 'email' => 'first@kampus.ac.id'])
            ->assertCreated()
            ->assertJsonPath('data.nim', '00000001');

        $this->actingAsUser($adminId)
            ->postJson($endpoint, [...$basePayload, 'email' => 'second@kampus.ac.id'])
            ->assertCreated()
            ->assertJsonPath('data.nim', '00000002');
    }

    public function test_lecturer_can_be_created_without_nidn_and_nidn_must_be_official_ten_digits_when_provided(): void
    {
        $adminId = $this->createUser('admin@example.test', 'admin');
        $departmentId = $this->createDepartment('IF', 'Informatika');

        $this->actingAsUser($adminId)
            ->postJson('/api/admin/dosen', [
                'email' => 'pending-nidn@kampus.ac.id',
                'password' => 'password-rahasia',
                'nama' => 'Dosen Tanpa NIDN',
                'jurusan_id' => $departmentId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nidn', null);

        $this->actingAsUser($adminId)
            ->postJson('/api/admin/dosen', [
                'email' => 'invalid-nidn@kampus.ac.id',
                'password' => 'password-rahasia',
                'nidn' => '12345',
                'nama' => 'Dosen NIDN Tidak Valid',
                'jurusan_id' => $departmentId,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nidn');
    }

    public function test_lecturer_list_supports_pagination(): void
    {
        $adminId = $this->createUser('admin@example.test', 'admin');
        $departmentId = $this->createDepartment('IF', 'Informatika');

        for ($count = 1; $count <= 11; $count += 1) {
            $lecturerUserId = $this->createUser(
                "lecturer-{$count}@kampus.ac.id",
                'dosen',
            );

            DB::table('dosen')->insert([
                'user_id' => $lecturerUserId,
                'nidn' => null,
                'nama' => 'Dosen '.str_pad((string) $count, 2, '0', STR_PAD_LEFT),
                'jurusan_id' => $departmentId,
                'created_at' => now(),
            ]);
        }

        $this->actingAsUser($adminId)
            ->getJson('/api/admin/dosen?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 11);

        $this->actingAsUser($adminId)
            ->getJson('/api/admin/dosen?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Dosen 11');
    }

    public function test_directory_write_endpoints_require_an_admin(): void
    {
        $studentId = $this->createUser('student@example.test', 'mahasiswa');

        $this->actingAsUser($studentId)
            ->postJson('/api/admin/jurusan', [
                'kode_jurusan' => 'IF',
                'nama_jurusan' => 'Informatika',
            ])
            ->assertForbidden();
    }

    private function createUser(string $email, string $role): int
    {
        return DB::table('users')->insertGetId([
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDepartment(string $code, string $name): int
    {
        return DB::table('jurusan')->insertGetId([
            'kode_jurusan' => $code,
            'nama_jurusan' => $name,
            'created_at' => now(),
        ]);
    }

    private function actingAsUser(int $userId): static
    {
        $user = \App\Models\User::findOrFail($userId);

        return $this->actingAs($user, 'sanctum');
    }
}
