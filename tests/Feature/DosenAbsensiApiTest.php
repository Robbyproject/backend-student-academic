<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DosenAbsensiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_lists_classes_grouped_filter_by_department(): void
    {
        [$lecturerId, $jurusanId, $classId] = $this->createLecturerClass();

        $otherDepartmentId = $this->createDepartment('SI', 'Sistem Informasi');

        $response = $this->actingAs( \App\Models\User::find($this->userId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas')
            ->assertOk()
            ->assertJsonCount(1);

        $response->assertJsonPath('0.id', $classId);
        $response->assertJsonPath('0.jurusan_id', $jurusanId);
        $response->assertJsonPath('0.nama_matkul', 'Game Development');
        $response->assertJsonPath('0.jumlah_peserta', 0);

        $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas?jurusan_id='.$otherDepartmentId)
            ->assertOk()
            ->assertJsonCount(0);

        $this->assertSame($lecturerId, (int) DB::table('kelas')->where('id', $classId)->value('dosen_id'));
    }

    public function test_participants_are_paginated_and_include_attendance_status(): void
    {
        [$lecturerId, $jurusanId, $classId] = $this->createLecturerClass();

        $studentIds = [];
        for ($count = 1; $count <= 11; $count += 1) {
            $studentIds[] = $this->enrollStudent($classId, $jurusanId, 2026, $count);
        }

        $first = DB::table('mahasiswa')->orderBy('nama')->first();

        // Absen salah satu mahasiswa terlebih dahulu.
        DB::table('absensi')->insert([
            'kelas_id' => $classId,
            'mahasiswa_id' => $first->id,
            'tanggal' => '2026-10-27',
            'status' => 'sakit',
            'created_at' => now(),
        ]);

        $response = $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas/'.$classId.'?tanggal=2026-10-27&page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 11);

        $response->assertJsonPath('kelas.id', $classId);
        $response->assertJsonPath('kelas.nama_jurusan', 'Informatika');
        $response->assertJsonPath('kelas.semester_label', 'Ganjil 2026/2027');
        $response->assertJsonPath('rekap.sakit', 1);
        $response->assertJsonPath('rekap.belum_dicatat', 10);

        $firstStudent = $response->json('data.0');
        $this->assertSame('sakit', $firstStudent['status']);
        $this->assertSame(1, $firstStudent['semester']);

        // Mahasiswa tanpa catatan absensi masih punya semester, tanpa status.
        $secondStudent = $response->json('data.1');
        $this->assertNull($secondStudent['status']);
        $this->assertSame(1, $secondStudent['semester']);

        $page2 = $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas/'.$classId.'?tanggal=2026-10-27&page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertNull($page2->json('data.0.status'));
    }

    public function test_lecturer_can_store_manual_attendance_and_overwrite(): void
    {
        [, $jurusanId, $classId] = $this->createLecturerClass();

        $studentIds = [];
        for ($count = 1; $count <= 3; $count += 1) {
            $studentIds[] = $this->enrollStudent($classId, $jurusanId, 2026, $count);
        }

        $payload = [
            'tanggal' => '2026-10-27',
            'entries' => [
                ['mahasiswa_id' => $studentIds[0], 'status' => 'hadir'],
                ['mahasiswa_id' => $studentIds[1], 'status' => 'izin'],
                ['mahasiswa_id' => $studentIds[2], 'status' => 'sakit'],
            ],
        ];

        $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->postJson('/api/dosen/absensi/kelas/'.$classId, $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Absensi berhasil disimpan.')
            ->assertJsonPath('rekap.hadir', 1)
            ->assertJsonPath('rekap.izin', 1)
            ->assertJsonPath('rekap.sakit', 1);

        $this->assertDatabaseHas('absensi', [
            'kelas_id' => $classId,
            'mahasiswa_id' => $studentIds[0],
            'tanggal' => '2026-10-27',
            'status' => 'hadir',
        ]);

        // Kirim ulang untuk mahasiswa pertama: harus overwrite, bukan duplikat.
        $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->postJson('/api/dosen/absensi/kelas/'.$classId, [
                'tanggal' => '2026-10-27',
                'entries' => [
                    ['mahasiswa_id' => $studentIds[0], 'status' => 'alpa'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('rekap.hadir', 0)
            ->assertJsonPath('rekap.alpa', 1);

        $this->assertSame(
            1,
            DB::table('absensi')
                ->where('kelas_id', $classId)
                ->where('mahasiswa_id', $studentIds[0])
                ->count()
        );
    }

    public function test_attendance_rejects_non_participant_and_invalid_status(): void
    {
        [, $jurusanId, $classId] = $this->createLecturerClass();

        $participant = $this->enrollStudent($classId, $jurusanId, 2026, 1);
        $outsiderId = $this->createStudentUser($jurusanId, 2);

        $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->postJson('/api/dosen/absensi/kelas/'.$classId, [
                'tanggal' => '2026-10-27',
                'entries' => [
                    ['mahasiswa_id' => $outsiderId, 'status' => 'hadir'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonMissing(['message' => null]);

        $this->assertDatabaseMissing('absensi', [
            'kelas_id' => $classId,
            'mahasiswa_id' => $participant,
        ]);

        $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->postJson('/api/dosen/absensi/kelas/'.$classId, [
                'tanggal' => '2026-10-27',
                'entries' => [
                    ['mahasiswa_id' => $participant, 'status' => 'libur'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries.0.status');
    }

    public function test_only_owner_lecturer_can_access_class_attendance(): void
    {
        [, $jurusanId, $classId] = $this->createLecturerClass();
        $participantId = $this->enrollStudent($classId, $jurusanId, 2026, 1);

        $otherDosenUserId = $this->createUser('other-dosen@kampus.ac.id', 'dosen');
        $otherDosenUserId;
        DB::table('dosen')->insertGetId([
            'user_id' => $otherDosenUserId,
            'nidn' => '9999999999',
            'nama' => 'Dosen Lain',
            'jurusan_id' => $this->jurusanId,
            'created_at' => now(),
        ]);

        $this->actingAs(\App\Models\User::find($otherDosenUserId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas/'.$classId.'?tanggal=2026-10-27')
            ->assertNotFound();

        $this->actingAs(\App\Models\User::find($otherDosenUserId), 'sanctum')
            ->postJson('/api/dosen/absensi/kelas/'.$classId, [
                'tanggal' => '2026-10-27',
                'entries' => [
                    ['mahasiswa_id' => $participantId, 'status' => 'hadir'],
                ],
            ])
            ->assertNotFound();
    }

    public function test_student_cannot_access_attendance_endpoints(): void
    {
        [, $jurusanId, $classId] = $this->createLecturerClass();

        $studentUserId = $this->createStudentUser($jurusanId, 1);

        $this->actingAs(\App\Models\User::find($studentUserId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas')
            ->assertForbidden();

        $this->actingAs(\App\Models\User::find($studentUserId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas/'.$classId.'?tanggal=2026-10-27')
            ->assertForbidden();
    }

    public function test_sessions_list_returns_summary_per_meeting(): void
    {
        [, $jurusanId, $classId] = $this->createLecturerClass();

        $a = $this->enrollStudent($classId, $jurusanId, 2026, 1);
        $b = $this->enrollStudent($classId, $jurusanId, 2026, 2);

        DB::table('absensi')->insert([
            ['kelas_id' => $classId, 'mahasiswa_id' => $a, 'tanggal' => '2026-10-20', 'status' => 'hadir', 'created_at' => now()],
            ['kelas_id' => $classId, 'mahasiswa_id' => $b, 'tanggal' => '2026-10-20', 'status' => 'alpa', 'created_at' => now()],
            ['kelas_id' => $classId, 'mahasiswa_id' => $a, 'tanggal' => '2026-10-27', 'status' => 'izin', 'created_at' => now()],
        ]);

        $this->actingAs(\App\Models\User::find($this->userId), 'sanctum')
            ->getJson('/api/dosen/absensi/kelas/'.$classId.'/pertemuan')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.tanggal', '2026-10-27')
            ->assertJsonPath('0.tercatat', 1)
            ->assertJsonPath('1.tercatat', 2)
            ->assertJsonPath('1.hadir', 1)
            ->assertJsonPath('1.alpa', 1);
    }

    // ---------- helpers ----------

    private function createLecturerClass(): array
    {
        $userId = $this->userId = $this->createUser('lecturer@kampus.ac.id', 'dosen');
        $jurusanId = $this->jurusanId = $this->createDepartment('IF', 'Informatika');
        $lecturerId = DB::table('dosen')->insertGetId([
            'user_id' => $userId,
            'nidn' => '1234567890',
            'nama' => 'Dosen Pengajar',
            'jurusan_id' => $jurusanId,
            'created_at' => now(),
        ]);
        $matkulId = DB::table('matkul')->insertGetId([
            'kode_matkul' => 'IF301',
            'nama_matkul' => 'Game Development',
            'sks' => 3,
            'jurusan_id' => $jurusanId,
            'created_at' => now(),
        ]);
        $classId = DB::table('kelas')->insertGetId([
            'matkul_id' => $matkulId,
            'dosen_id' => $lecturerId,
            'nama_kelas' => 'GD-A',
            'tahun_ajaran' => '2026/2027',
            'term' => 'ganjil',
            'created_at' => now(),
        ]);

        return [$lecturerId, $jurusanId, $classId];
    }

    private function enrollStudent(int $classId, int $jurusanId, int $angkatan, int $count): int
    {
        $studentUserId = $this->createStudentUser($jurusanId, $count);
        $mahasiswaId = DB::table('mahasiswa')->insertGetId([
            'user_id' => $studentUserId,
            'nim' => str_pad((string) $count, 8, '0', STR_PAD_LEFT),
            'nama' => 'Mahasiswa '.str_pad((string) $count, 2, '0', STR_PAD_LEFT),
            'jurusan_id' => $jurusanId,
            'angkatan' => $angkatan,
            'created_at' => now(),
        ]);
        DB::table('peserta_kelas')->insert([
            'kelas_id' => $classId,
            'mahasiswa_id' => $mahasiswaId,
            'created_at' => now(),
        ]);

        return $mahasiswaId;
    }

    private function createStudentUser(int $jurusanId, int $count): int
    {
        return $this->createUser("student-{$count}@kampus.ac.id", 'mahasiswa');
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
}
