<?php

namespace Tests\Feature;

use Database\Seeders\StudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class StudentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_student_account_and_profile_and_can_be_rerun(): void
    {
        DB::table('jurusan')->insert([
            'kode_jurusan' => 'IF',
            'nama_jurusan' => 'Informatika',
            'created_at' => now(),
        ]);

        $this->withSeederPassword('temporary-test-password', function (): void {
            $seeder = new StudentSeeder;
            $seeder->run();
            $seeder->run();
        });

        $user = DB::table('users')->where('email', 'robby@gmail.com')->first();
        $student = DB::table('mahasiswa')->where('user_id', $user->id)->first();

        $this->assertSame('mahasiswa', $user->role);
        $this->assertTrue(Hash::check('temporary-test-password', $user->password));
        $this->assertSame('TEST-ROBBY-2026', $student->nim);
        $this->assertSame('Robby', $student->nama);
        $this->assertSame(2026, $student->angkatan);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('mahasiswa', 1);
    }

    public function test_it_requires_the_password_to_be_provided_through_the_environment(): void
    {
        $this->withSeederPassword(null, function (): void {
            $this->expectException(RuntimeException::class);
            (new StudentSeeder)->run();
        });

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('mahasiswa', 0);
    }

    private function withSeederPassword(?string $password, callable $callback): void
    {
        $previous = getenv('STUDENT_PASSWORD');

        if ($password === null) {
            putenv('STUDENT_PASSWORD');
        } else {
            putenv('STUDENT_PASSWORD='.$password);
        }

        try {
            $callback();
        } finally {
            if ($previous === false) {
                putenv('STUDENT_PASSWORD');
            } else {
                putenv('STUDENT_PASSWORD='.$previous);
            }
        }
    }
}
