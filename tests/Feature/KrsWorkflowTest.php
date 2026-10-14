<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KrsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_course_and_add_it_to_a_curriculum_package(): void
    {
        $adminId = DB::table('users')->insertGetId([
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $departmentId = DB::table('jurusan')->insertGetId([
            'kode_jurusan' => 'IF',
            'nama_jurusan' => 'Informatika',
            'created_at' => now(),
        ]);

        $admin = User::findOrFail($adminId);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/matkul', [
                'kode_matkul' => 'IF201',
                'nama_matkul' => 'Struktur Data',
                'sks' => 3,
                'jurusan_id' => $departmentId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode_matkul', 'IF201')
            ->assertJsonPath('data.jurusan_id', $departmentId);

        $courseId = DB::table('matkul')->where('kode_matkul', 'IF201')->value('id');

        $this->postJson('/api/admin/krs/packages', [
            'jurusan_id' => $departmentId,
            'semester' => 1,
            'name' => 'Paket Semester 1',
            'matkul_ids' => [$courseId],
        ])->assertCreated();

        $this->getJson('/api/admin/academic-data')
            ->assertOk()
            ->assertJsonFragment([
                'kode_matkul' => 'IF201',
                'nama_matkul' => 'Struktur Data',
            ]);
    }

    public function test_admin_can_configure_period_and_package_and_student_can_submit_krs(): void
    {
        [$student, $classIds] = $this->createKrsFixture();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/admin/krs/packages')
            ->assertForbidden();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/krs/options')
            ->assertOk()
            ->assertJsonPath('semester', 3)
            ->assertJsonPath('package.courses.0.classes.0.id', $classIds[0]);

        $this->putJson('/api/krs', ['kelas_ids' => $classIds])
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('total_sks', 6);

        $this->getJson('/api/krs')
            ->assertOk()
            ->assertJsonPath('krs.status', 'approved')
            ->assertJsonCount(2, 'krs.courses');

        $this->putJson('/api/krs', ['kelas_ids' => $classIds])->assertOk();

        $this->assertDatabaseCount('peserta_kelas', 2);
        $this->assertDatabaseCount('krs_details', 2);
        $this->assertDatabaseCount('krs', 1);

        $periodId = DB::table('krs_periods')->value('id');
        $this->actingAs(User::where('role', 'admin')->firstOrFail(), 'sanctum')
            ->patchJson('/api/admin/krs/periods/'.$periodId, ['registration_open' => false])
            ->assertOk();

        $this->actingAs($student, 'sanctum')
            ->putJson('/api/krs', ['kelas_ids' => $classIds])
            ->assertConflict();

        $this->assertDatabaseCount('krs', 1);
    }

    public function test_student_cannot_submit_a_package_with_conflicting_class_schedules(): void
    {
        [$student, $classIds] = $this->createKrsFixture(true);

        $this->actingAs($student, 'sanctum')
            ->putJson('/api/krs', ['kelas_ids' => $classIds])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kelas_ids');

        $this->assertDatabaseCount('krs', 0);
        $this->assertDatabaseCount('peserta_kelas', 0);
    }

    private function createKrsFixture(bool $conflictingSchedules = false): array
    {
        $adminId = DB::table('users')->insertGetId([
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $departmentId = DB::table('jurusan')->insertGetId([
            'kode_jurusan' => 'IF',
            'nama_jurusan' => 'Informatika',
            'created_at' => now(),
        ]);

        $courseIds = [];
        foreach ([['IF101', 'Algoritma'], ['IF102', 'Basis Data']] as [$code, $name]) {
            $courseIds[] = DB::table('matkul')->insertGetId([
                'kode_matkul' => $code,
                'nama_matkul' => $name,
                'sks' => 3,
                'jurusan_id' => $departmentId,
                'created_at' => now(),
            ]);
        }

        $this->actingAs(User::findOrFail($adminId), 'sanctum')
            ->postJson('/api/admin/krs/periods', [
                'academic_year_start' => 2026,
                'term' => 'ganjil',
                'is_active' => true,
                'registration_open' => true,
            ])
            ->assertCreated();

        $this->postJson('/api/admin/krs/packages', [
            'jurusan_id' => $departmentId,
            'semester' => 3,
            'name' => 'Paket Semester 3',
            'matkul_ids' => $courseIds,
        ])->assertCreated();

        $dosenUserId = DB::table('users')->insertGetId([
            'email' => 'lecturer@example.test',
            'password' => Hash::make('password'),
            'role' => 'dosen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dosenId = DB::table('dosen')->insertGetId([
            'user_id' => $dosenUserId,
            'nidn' => '1234567890',
            'nama' => 'Dosen Tes',
            'jurusan_id' => $departmentId,
            'created_at' => now(),
        ]);

        $classIds = [];
        foreach ($courseIds as $index => $courseId) {
            $classId = DB::table('kelas')->insertGetId([
                'matkul_id' => $courseId,
                'dosen_id' => $dosenId,
                'nama_kelas' => 'Kelas '.($index + 1),
                'tahun_ajaran' => '2026/2027',
                'term' => 'ganjil',
                'created_at' => now(),
            ]);
            $classIds[] = $classId;

            DB::table('jadwal')->insert([
                'kelas_id' => $classId,
                'hari' => 'Senin',
                'jam_mulai' => $conflictingSchedules ? '08:00:00' : sprintf('%02d:00:00', 8 + ($index * 2)),
                'jam_selesai' => $conflictingSchedules ? '10:00:00' : sprintf('%02d:00:00', 10 + ($index * 2)),
                'ruangan' => 'Ruang '.($index + 1),
                'created_at' => now(),
            ]);
        }

        $studentUserId = DB::table('users')->insertGetId([
            'email' => 'student@example.test',
            'password' => Hash::make('password'),
            'role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mahasiswa')->insert([
            'user_id' => $studentUserId,
            'nim' => '202500001',
            'nama' => 'Mahasiswa Tes',
            'jurusan_id' => $departmentId,
            'angkatan' => 2025,
            'created_at' => now(),
        ]);

        return [User::findOrFail($studentUserId), $classIds];
    }
}
