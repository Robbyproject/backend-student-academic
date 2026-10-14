<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAcademicController extends Controller
{
    public function data(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'jurusan' => DB::table('jurusan')
                ->select(
                    'id',
                    'kode_jurusan',
                    'nama_jurusan'
                )
                ->orderBy('nama_jurusan')
                ->get(),

            'dosen' => DB::table('dosen')
                ->select(
                    'id',
                    'nama',
                    'nidn',
                    'jurusan_id'
                )
                ->orderBy('nama')
                ->get(),

            'matkul' => DB::table('matkul')
                ->select(
                    'id',
                    'kode_matkul',
                    'nama_matkul',
                    'sks',
                    'jurusan_id'
                )
                ->orderBy('nama_matkul')
                ->get(),
        ]);
    }

    public function storeCourse(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'kode_matkul' => ['required', 'string', 'max:50', 'unique:matkul,kode_matkul'],
            'nama_matkul' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'integer', 'between:1,6'],
            'jurusan_id' => ['required', 'integer', 'exists:jurusan,id'],
        ]);

        $courseId = DB::table('matkul')->insertGetId([
            'kode_matkul' => $validated['kode_matkul'],
            'nama_matkul' => $validated['nama_matkul'],
            'sks' => $validated['sks'],
            'jurusan_id' => $validated['jurusan_id'],
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Mata kuliah berhasil dibuat.',
            'data' => [
                'id' => $courseId,
                'kode_matkul' => $validated['kode_matkul'],
                'nama_matkul' => $validated['nama_matkul'],
                'sks' => $validated['sks'],
                'jurusan_id' => $validated['jurusan_id'],
            ],
        ], 201);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'dosen_id' => 'required|integer|min:1',
            'matkul_id' => 'required|integer|min:1',
            'nama_kelas' => 'required|string|max:255',
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'term' => 'required|in:ganjil,genap',

            'hari' => 'required|string|max:20',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'ruangan' => 'required|string|max:50',
        ]);

        [$yearStart, $yearEnd] = explode('/', $validated['tahun_ajaran']);
        if ((int) $yearEnd !== (int) $yearStart + 1) {
            throw ValidationException::withMessages([
                'tahun_ajaran' => 'Tahun ajaran harus berupa dua tahun berurutan, misalnya 2026/2027.',
            ]);
        }

        $dosen = DB::table('dosen')
            ->where('id', $validated['dosen_id'])
            ->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Dosen tidak ditemukan.',
            ], 404);
        }

        $matkul = DB::table('matkul')
            ->where('id', $validated['matkul_id'])
            ->first();

        if (! $matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan.',
            ], 404);
        }

        // Pastikan dosen dan mata kuliah berasal
        // dari jurusan yang sama.
        if ((int) $dosen->jurusan_id !== (int) $matkul->jurusan_id) {
            return response()->json([
                'message' => 'Dosen dan mata kuliah harus berasal dari jurusan yang sama.',
            ], 422);
        }

        $kelasId = DB::transaction(function () use ($validated) {
            $kelasId = DB::table('kelas')->insertGetId([
                'matkul_id' => $validated['matkul_id'],
                'dosen_id' => $validated['dosen_id'],
                'nama_kelas' => $validated['nama_kelas'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'term' => $validated['term'],
            ]);

            DB::table('jadwal')->insert([
                'kelas_id' => $kelasId,
                'hari' => $validated['hari'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'ruangan' => $validated['ruangan'],
                'created_at' => now(),
            ]);

            return $kelasId;
        });

        return response()->json([
            'message' => 'Kelas dan jadwal berhasil dibuat.',
            'kelas_id' => $kelasId,
        ], 201);
    }

    public function updateClassOffering(Request $request, int $classId)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'term' => ['required', 'in:ganjil,genap'],
        ]);

        [$yearStart, $yearEnd] = explode('/', $validated['tahun_ajaran']);
        if ((int) $yearEnd !== (int) $yearStart + 1) {
            throw ValidationException::withMessages([
                'tahun_ajaran' => 'Tahun ajaran harus berupa dua tahun berurutan, misalnya 2026/2027.',
            ]);
        }

        $class = DB::table('kelas')->where('id', $classId)->first();
        abort_if(! $class, 404, 'Kelas tidak ditemukan.');

        DB::table('kelas')->where('id', $classId)->update([
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'term' => $validated['term'],
        ]);

        return response()->json(['message' => 'Periode kelas berhasil diperbarui.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya admin yang dapat mengatur data akademik.');
    }
}
