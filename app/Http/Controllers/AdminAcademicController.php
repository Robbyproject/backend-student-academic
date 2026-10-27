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
            'jam_mulai' => 'required|string',
            'jam_selesai' => 'required|string',
            'ruangan' => 'required|string|max:50',
        ]);

        [$yearStart, $yearEnd] = explode('/', $validated['tahun_ajaran']);
        if ((int) $yearEnd !== (int) $yearStart + 1) {
            throw ValidationException::withMessages([
                'tahun_ajaran' => 'Tahun ajaran harus berupa dua tahun berurutan, misalnya 2026/2027.',
            ]);
        }

        $jamMulai = trim($validated['jam_mulai']);
        $jamSelesai = trim($validated['jam_selesai']);

        if (strtotime($jamSelesai) === false || strtotime($jamMulai) === false || strtotime($jamSelesai) <= strtotime($jamMulai)) {
            throw ValidationException::withMessages([
                'jam_selesai' => 'Jam selesai harus lebih besar dari jam mulai.',
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

        // Pastikan dosen dan mata kuliah berasal dari jurusan yang sama.
        if ((int) $dosen->jurusan_id !== (int) $matkul->jurusan_id) {
            return response()->json([
                'message' => 'Dosen dan mata kuliah harus berasal dari jurusan yang sama.',
            ], 422);
        }

        $hari = trim($validated['hari']);
        $ruangan = trim($validated['ruangan']);

        $kelasId = DB::transaction(function () use ($validated, $dosen, $hari, $ruangan, $jamMulai, $jamSelesai) {
            // Validasi bentrok ruangan dan dosen sebelum menyimpan kelas & jadwal
            $this->ensureNoScheduleConflict(
                hari: $hari,
                jamMulai: $jamMulai,
                jamSelesai: $jamSelesai,
                ruangan: $ruangan,
                dosenId: (int) $validated['dosen_id'],
                namaDosen: $dosen->nama
            );

            $kelasId = DB::table('kelas')->insertGetId([
                'matkul_id' => $validated['matkul_id'],
                'dosen_id' => $validated['dosen_id'],
                'nama_kelas' => trim($validated['nama_kelas']),
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'term' => $validated['term'],
            ]);

            DB::table('jadwal')->insert([
                'kelas_id' => $kelasId,
                'hari' => $hari,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'ruangan' => $ruangan,
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

    /**
     * Cek apakah ada jadwal yang bentrok pada hari dan rentang jam yang beririsan:
     * 1. Ruangan yang sama tidak boleh dipakai dua kelas pada waktu yang bertabrakan.
     * 2. Dosen yang sama tidak boleh mengajar dua kelas pada waktu yang bertabrakan.
     */
    private function ensureNoScheduleConflict(
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        string $ruangan,
        int $dosenId,
        string $namaDosen,
        ?int $ignoreKelasId = null
    ): void {
        // 1. Cek bentrok ruangan pada hari dan waktu yang beririsan
        $roomConflictQuery = DB::table('jadwal as j')
            ->join('kelas as k', 'k.id', '=', 'j.kelas_id')
            ->join('matkul as m', 'm.id', '=', 'k.matkul_id')
            ->whereRaw('LOWER(TRIM(j.hari)) = LOWER(TRIM(?))', [$hari])
            ->whereRaw('LOWER(TRIM(j.ruangan)) = LOWER(TRIM(?))', [$ruangan])
            ->where('j.jam_mulai', '<', $jamSelesai)
            ->where('j.jam_selesai', '>', $jamMulai)
            ->select(
                'k.nama_kelas',
                'm.nama_matkul',
                'j.hari',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.ruangan'
            );

        if ($ignoreKelasId !== null) {
            $roomConflictQuery->where('k.id', '!=', $ignoreKelasId);
        }

        $roomConflict = $roomConflictQuery->first();

        if ($roomConflict) {
            $msg = sprintf(
                'Jadwal bentrok: Ruangan %s sudah digunakan untuk kelas %s (%s) pada hari %s pukul %s - %s.',
                $roomConflict->ruangan,
                $roomConflict->nama_matkul,
                $roomConflict->nama_kelas,
                $roomConflict->hari,
                substr((string) $roomConflict->jam_mulai, 0, 5),
                substr((string) $roomConflict->jam_selesai, 0, 5)
            );

            throw ValidationException::withMessages([
                'ruangan' => [$msg],
                'jadwal' => [$msg],
            ]);
        }

        // 2. Cek bentrok jadwal mengajar dosen pada hari dan waktu yang beririsan
        $dosenConflictQuery = DB::table('jadwal as j')
            ->join('kelas as k', 'k.id', '=', 'j.kelas_id')
            ->join('matkul as m', 'm.id', '=', 'k.matkul_id')
            ->whereRaw('LOWER(TRIM(j.hari)) = LOWER(TRIM(?))', [$hari])
            ->where('k.dosen_id', $dosenId)
            ->where('j.jam_mulai', '<', $jamSelesai)
            ->where('j.jam_selesai', '>', $jamMulai)
            ->select(
                'k.nama_kelas',
                'm.nama_matkul',
                'j.hari',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.ruangan'
            );

        if ($ignoreKelasId !== null) {
            $dosenConflictQuery->where('k.id', '!=', $ignoreKelasId);
        }

        $dosenConflict = $dosenConflictQuery->first();

        if ($dosenConflict) {
            $msg = sprintf(
                'Jadwal bentrok: Dosen %s sudah mengajar kelas %s (%s) di ruangan %s pada hari %s pukul %s - %s.',
                $namaDosen,
                $dosenConflict->nama_matkul,
                $dosenConflict->nama_kelas,
                $dosenConflict->ruangan,
                $dosenConflict->hari,
                substr((string) $dosenConflict->jam_mulai, 0, 5),
                substr((string) $dosenConflict->jam_selesai, 0, 5)
            );

            throw ValidationException::withMessages([
                'dosen_id' => [$msg],
                'jadwal' => [$msg],
            ]);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya admin yang dapat mengatur data akademik.');
    }
}