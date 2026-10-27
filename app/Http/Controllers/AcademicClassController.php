<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (
            !$user ||
            !in_array($user->role, ['admin', 'mahasiswa', 'dosen'], true)
        ) {
            abort(403, 'Role tidak memiliki akses ke kelas akademik.');
        }

        $perPage = min(
            max((int) $request->query('per_page', 10), 1),
            100
        );

        $query = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'k.id',
                'k.matkul_id',
                'k.dosen_id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'mk.jurusan_id',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen'
            );

        if ($user->role === 'mahasiswa') {
            $mahasiswa = DB::table('mahasiswa')
                ->where('user_id', $user->id)
                ->first(['id', 'jurusan_id']);

            if (!$mahasiswa) {
                return response()->json([
                    'message' => 'Profil mahasiswa tidak ditemukan.',
                ], 404);
            }

            $query->where('mk.jurusan_id', $mahasiswa->jurusan_id);
        }

        if ($user->role === 'dosen') {
            $dosen = DB::table('dosen')
                ->where('user_id', $user->id)
                ->first(['id']);

            if (!$dosen) {
                return response()->json([
                    'message' => 'Profil dosen tidak ditemukan.',
                ], 404);
            }

            $query->where('k.dosen_id', $dosen->id);
        }

        $paginator = $query
            ->orderBy('mk.nama_matkul')
            ->orderBy('k.nama_kelas')
            ->orderBy('k.id')
            ->paginate($perPage)
            ->withQueryString();

        $classIds = $paginator->getCollection()
            ->pluck('id')
            ->all();

        $schedulesByClass = collect();

        if (!empty($classIds)) {
            $schedulesByClass = DB::table('jadwal')
                ->whereIn('kelas_id', $classIds)
                ->orderByRaw("
                    CASE LOWER(TRIM(hari))
                        WHEN 'senin' THEN 1
                        WHEN 'selasa' THEN 2
                        WHEN 'rabu' THEN 3
                        WHEN 'kamis' THEN 4
                        WHEN 'jumat' THEN 5
                        WHEN 'sabtu' THEN 6
                        WHEN 'minggu' THEN 7
                        ELSE 8
                    END
                ")
                ->orderBy('jam_mulai')
                ->get()
                ->groupBy('kelas_id');
        }

        $paginator->getCollection()->transform(
            function ($class) use ($schedulesByClass) {
                $schedules = $schedulesByClass
                    ->get($class->id, collect())
                    ->map(function ($schedule) {
                        return $schedule->hari . ' ' .
                            substr($schedule->jam_mulai, 0, 5) . ' - ' .
                            substr($schedule->jam_selesai, 0, 5) .
                            ' (' . ($schedule->ruangan ?? '-') . ')';
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $class->id,
                    'matkul_id' => $class->matkul_id,
                    'dosen_id' => $class->dosen_id,
                    'jurusan_id' => $class->jurusan_id,
                    'nama_kelas' => $class->nama_kelas,
                    'tahun_ajaran' => $class->tahun_ajaran,
                    'term' => $class->term,
                    'kode_matkul' => $class->kode_matkul,
                    'nama_matkul' => $class->nama_matkul,
                    'sks' => $class->sks,
                    'nama_dosen' => $class->nama_dosen,
                    'schedules' => $schedules,
                ];
            }
        );

        return response()->json($paginator);
    }

    /**
     * Mengambil daftar kelas berdasarkan jurusan untuk dropdown form tugas.
     */
    public function getClassesByJurusan(Request $request, int $jurusanId)
    {
        $classes = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->where('mk.jurusan_id', $jurusanId)
            ->select(
                'k.id',
                'k.nama_kelas',
                'mk.nama_matkul',
                'd.nama as nama_dosen'
            )
            ->orderBy('k.nama_kelas')
            ->get();

        return response()->json($classes);
    }
}