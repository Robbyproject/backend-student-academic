<?php

namespace App\Http\Controllers;

use App\Rules\CampusEmail;
use App\Services\StudentNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminDirectoryController extends Controller
{
    public function departments(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json(
            DB::table('jurusan')
                ->select('id', 'kode_jurusan', 'nama_jurusan')
                ->orderBy('nama_jurusan')
                ->get()
        );
    }

    public function department(Request $request, int $jurusanId)
    {
        $this->authorizeAdmin($request);

        $department = DB::table('jurusan')
            ->select('id', 'kode_jurusan', 'nama_jurusan')
            ->where('id', $jurusanId)
            ->first();

        abort_if(! $department, 404, 'Jurusan tidak ditemukan.');

        return response()->json($department);
    }

    public function storeDepartment(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'kode_jurusan' => ['required', 'string', 'max:50', 'unique:jurusan,kode_jurusan'],
            'nama_jurusan' => ['required', 'string', 'max:255'],
        ]);

        $id = DB::table('jurusan')->insertGetId([
            ...$validated,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Jurusan berhasil dibuat.',
            'data' => ['id' => $id, ...$validated],
        ], 201);
    }

    public function updateDepartment(Request $request, int $jurusanId)
    {
        $this->authorizeAdmin($request);

        abort_if(! DB::table('jurusan')->where('id', $jurusanId)->exists(), 404, 'Jurusan tidak ditemukan.');

        $required = $request->isMethod('put') ? 'required' : 'sometimes';
        $validated = $request->validate([
            'kode_jurusan' => [
                $required,
                'string',
                'max:50',
                Rule::unique('jurusan', 'kode_jurusan')->ignore($jurusanId),
            ],
            'nama_jurusan' => [$required, 'string', 'max:255'],
        ]);

        DB::table('jurusan')->where('id', $jurusanId)->update($validated);

        return response()->json([
            'message' => 'Jurusan berhasil diperbarui.',
            'data' => ['id' => $jurusanId, ...$validated],
        ]);
    }

    public function destroyDepartment(Request $request, int $jurusanId)
    {
        $this->authorizeAdmin($request);

        abort_if(! DB::table('jurusan')->where('id', $jurusanId)->exists(), 404, 'Jurusan tidak ditemukan.');

        $inUse = DB::table('dosen')->where('jurusan_id', $jurusanId)->exists()
            || DB::table('matkul')->where('jurusan_id', $jurusanId)->exists()
            || DB::table('mahasiswa')->where('jurusan_id', $jurusanId)->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Jurusan tidak dapat dihapus karena masih digunakan oleh dosen, mata kuliah, atau mahasiswa.',
            ], 409);
        }

        DB::table('jurusan')->where('id', $jurusanId)->delete();

        return response()->json(['message' => 'Jurusan berhasil dihapus.']);
    }

    public function lecturers(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'jurusan_id' => ['sometimes', 'integer', 'exists:jurusan,id'],
        ]);

        $query = DB::table('dosen as d')
            ->join('jurusan as j', 'j.id', '=', 'd.jurusan_id')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->select(
                'd.id',
                'd.user_id',
                'd.nidn',
                'd.nama',
                'd.jurusan_id',
                'j.kode_jurusan',
                'j.nama_jurusan',
                'u.email'
            )
            ->orderBy('d.nama');

        if (isset($validated['jurusan_id'])) {
            $query->where('d.jurusan_id', $validated['jurusan_id']);
        }

        return response()->json($query->get());
    }

    public function lecturer(Request $request, int $dosenId)
    {
        $this->authorizeAdmin($request);

        $lecturer = DB::table('dosen as d')
            ->join('jurusan as j', 'j.id', '=', 'd.jurusan_id')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->select(
                'd.id',
                'd.user_id',
                'd.nidn',
                'd.nama',
                'd.jurusan_id',
                'j.kode_jurusan',
                'j.nama_jurusan',
                'u.email'
            )
            ->where('d.id', $dosenId)
            ->first();

        abort_if(! $lecturer, 404, 'Dosen tidak ditemukan.');

        return response()->json($lecturer);
    }

    public function storeLecturer(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email', new CampusEmail],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'nidn' => ['sometimes', 'nullable', 'digits:10', 'unique:dosen,nidn'],
            'nama' => ['required', 'string', 'max:255'],
            'jurusan_id' => ['required', 'integer', 'exists:jurusan,id'],
        ]);

        $lecturer = DB::transaction(function () use ($validated) {
            $now = now();
            $userId = DB::table('users')->insertGetId([
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'dosen',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $lecturerId = DB::table('dosen')->insertGetId([
                'user_id' => $userId,
                'nidn' => $validated['nidn'] ?? null,
                'nama' => $validated['nama'],
                'jurusan_id' => $validated['jurusan_id'],
                'created_at' => $now,
            ]);

            return ['id' => $lecturerId, 'user_id' => $userId];
        });

        return response()->json([
            'message' => 'Dosen berhasil dibuat.',
            'data' => [
                'id' => $lecturer['id'],
                'user_id' => $lecturer['user_id'],
                'nama' => $validated['nama'],
                'nidn' => $validated['nidn'] ?? null,
                'email' => $validated['email'],
                'jurusan_id' => $validated['jurusan_id'],
            ],
        ], 201);
    }

    public function storeStudentUser(Request $request, StudentNumberGenerator $nimGenerator)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email', new CampusEmail],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'role' => ['sometimes', 'required', Rule::in(['mahasiswa'])],
            'jurusan_id' => ['required', 'integer', 'exists:jurusan,id'],
            'angkatan' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $student = DB::transaction(function () use ($validated, $nimGenerator) {
            $now = now();
            $userId = DB::table('users')->insertGetId([
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'mahasiswa',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $nim = $nimGenerator->next();
            $studentId = DB::table('mahasiswa')->insertGetId([
                'user_id' => $userId,
                'nim' => $nim,
                'nama' => $validated['nama'],
                'jurusan_id' => $validated['jurusan_id'],
                'angkatan' => $validated['angkatan'],
                'created_at' => $now,
            ]);

            return [
                'user_id' => $userId,
                'mahasiswa_id' => $studentId,
                'nim' => $nim,
            ];
        });

        return response()->json([
            'message' => 'Akun dan profil dasar mahasiswa berhasil dibuat.',
            'data' => [
                'id' => $student['user_id'],
                'mahasiswa_id' => $student['mahasiswa_id'],
                'nim' => $student['nim'],
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'role' => 'mahasiswa',
                'jurusan_id' => $validated['jurusan_id'],
                'angkatan' => $validated['angkatan'],
            ],
        ], 201);
    }

    public function updateLecturer(Request $request, int $dosenId)
    {
        $this->authorizeAdmin($request);

        $lecturer = DB::table('dosen')->where('id', $dosenId)->first();
        abort_if(! $lecturer, 404, 'Dosen tidak ditemukan.');

        $required = $request->isMethod('put') ? 'required' : 'sometimes';
        $validated = $request->validate([
            'email' => array_merge(
                [$required, 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($lecturer->user_id)],
                [new CampusEmail]
            ),
            'password' => ['sometimes', 'required', 'string', 'min:6', 'max:255'],
            'nidn' => [
                'sometimes',
                'nullable',
                'digits:10',
                Rule::unique('dosen', 'nidn')->ignore($dosenId),
            ],
            'nama' => [$required, 'string', 'max:255'],
            'jurusan_id' => [$required, 'integer', 'exists:jurusan,id'],
        ]);

        if (isset($validated['jurusan_id'])) {
            $hasMismatchedCourse = DB::table('kelas as k')
                ->join('matkul as m', 'm.id', '=', 'k.matkul_id')
                ->where('k.dosen_id', $dosenId)
                ->where('m.jurusan_id', '!=', $validated['jurusan_id'])
                ->exists();

            if ($hasMismatchedCourse) {
                return response()->json([
                    'message' => 'Jurusan dosen tidak dapat diubah karena dosen masih mengajar mata kuliah dari jurusan lain.',
                ], 409);
            }
        }

        DB::transaction(function () use ($validated, $lecturer, $dosenId) {
            $lecturerChanges = array_intersect_key($validated, array_flip(['nidn', 'nama', 'jurusan_id']));
            if ($lecturerChanges !== []) {
                DB::table('dosen')->where('id', $dosenId)->update($lecturerChanges);
            }

            $userChanges = [];
            if (isset($validated['email'])) {
                $userChanges['email'] = $validated['email'];
            }
            if (isset($validated['password'])) {
                $userChanges['password'] = Hash::make($validated['password']);
            }
            if (isset($validated['nama'])) {
                $userChanges['nama'] = $validated['nama'];
            }
            if ($userChanges !== []) {
                $userChanges['updated_at'] = now();
                DB::table('users')->where('id', $lecturer->user_id)->update($userChanges);
            }
        });

        return response()->json([
            'message' => 'Dosen berhasil diperbarui.',
            'data' => [
                'id' => $dosenId,
                'nama' => $validated['nama'] ?? $lecturer->nama,
                'nidn' => array_key_exists('nidn', $validated) ? $validated['nidn'] : $lecturer->nidn,
                'email' => $validated['email'] ?? DB::table('users')->where('id', $lecturer->user_id)->value('email'),
                'jurusan_id' => $validated['jurusan_id'] ?? $lecturer->jurusan_id,
            ],
        ]);
    }

    public function destroyLecturer(Request $request, int $dosenId)
    {
        $this->authorizeAdmin($request);

        $lecturer = DB::table('dosen')->where('id', $dosenId)->first();
        abort_if(! $lecturer, 404, 'Dosen tidak ditemukan.');

        if (DB::table('kelas')->where('dosen_id', $dosenId)->exists()) {
            return response()->json([
                'message' => 'Dosen tidak dapat dihapus karena masih digunakan oleh kelas.',
            ], 409);
        }

        DB::table('users')->where('id', $lecturer->user_id)->delete();

        return response()->json(['message' => 'Dosen berhasil dihapus.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya admin yang dapat mengatur data akademik.');
    }
}
