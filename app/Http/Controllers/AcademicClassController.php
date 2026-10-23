<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicClassController extends Controller
{
    // Mengambil kelas dengan pagination JSON
    public function index(Request $request)
    {
        $perPage = min(
            max((int) $request->query('per_page', 10), 1),
            100
        );

        $paginator = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'k.id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen'
            )
            ->orderBy('mk.nama_matkul')
            ->orderBy('k.id')
            ->paginate($perPage);

        $classIds = $paginator->getCollection()
            ->pluck('id')
            ->all();

        $schedulesByClass = collect();

        if (!empty($classIds)) {
            $schedulesByClass = DB::table('jadwal')
                ->whereIn('kelas_id', $classIds)
                ->orderBy('hari')
                ->orderBy('jam_mulai')
                ->get()
                ->groupBy('kelas_id');
        }

        $paginator->getCollection()->transform(
            function ($class) use ($schedulesByClass) {
                $schedules = $schedulesByClass
                    ->get($class->id, collect())
                    ->map(function ($item) {
                        return $item->hari . ' ' .
                            substr($item->jam_mulai, 0, 5) . ' - ' .
                            substr($item->jam_selesai, 0, 5) .
                            ' (' . $item->ruangan . ')';
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $class->id,
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

    // Mengambil detail satu kelas
    public function show(string $id)
    {
        $class = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->where('k.id', $id)
            ->select(
                'k.id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen'
            )
            ->first();

        if (!$class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan',
            ], 404);
        }

        $jumlahMahasiswa = DB::table('peserta_kelas')
            ->where('kelas_id', $id)
            ->count();

        return response()->json([
            'id' => $class->id,
            'nama_kelas' => $class->nama_kelas,
            'tahun_ajaran' => $class->tahun_ajaran,
            'term' => $class->term,
            'kode_matkul' => $class->kode_matkul,
            'nama_matkul' => $class->nama_matkul,
            'sks' => $class->sks,
            'nama_dosen' => $class->nama_dosen,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
        ]);
    }

    // Mengambil dosen sesuai jurusan mata kuliah
    public function lecturersByCourse(string $matkulId)
    {
        $matkul = DB::table('matkul')
            ->where('id', $matkulId)
            ->first();

        if (!$matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan',
            ], 404);
        }

        $dosen = DB::table('dosen')
            ->where('jurusan_id', $matkul->jurusan_id)
            ->select('id', 'nama', 'nidn')
            ->orderBy('nama')
            ->get();

        return response()->json($dosen);
    }

    // Membuat kelas baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'matkul_id' => ['required', 'integer', 'min:1'],
            'dosen_id' => ['required', 'integer', 'min:1'],
            'nama_kelas' => ['required', 'string', 'max:255'],
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'term' => ['required', 'in:ganjil,genap'],
        ]);

        [$yearStart, $yearEnd] = explode(
            '/',
            $validated['tahun_ajaran']
        );

        if ((int) $yearEnd !== (int) $yearStart + 1) {
            throw ValidationException::withMessages([
                'tahun_ajaran' =>
                    'Tahun ajaran harus berupa dua tahun berurutan, misalnya 2026/2027.',
            ]);
        }

        $matkul = DB::table('matkul')
            ->where('id', $validated['matkul_id'])
            ->first();

        if (!$matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan',
            ], 404);
        }

        $dosen = DB::table('dosen')
            ->where('id', $validated['dosen_id'])
            ->first();

        if (!$dosen) {
            return response()->json([
                'message' => 'Dosen tidak ditemukan',
            ], 404);
        }

        if ((int) $dosen->jurusan_id !== (int) $matkul->jurusan_id) {
            return response()->json([
                'message' =>
                    'Dosen tidak berasal dari jurusan mata kuliah tersebut',
            ], 422);
        }

        $id = DB::table('kelas')->insertGetId([
            'matkul_id' => $validated['matkul_id'],
            'dosen_id' => $validated['dosen_id'],
            'nama_kelas' => $validated['nama_kelas'],
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'term' => $validated['term'],
        ]);

        return response()->json([
            'message' => 'Kelas berhasil dibuat',
            'data' => [
                'id' => $id,
                'nama_kelas' => $validated['nama_kelas'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'term' => $validated['term'],
                'matkul_id' => $validated['matkul_id'],
                'dosen_id' => $validated['dosen_id'],
            ],
        ], 201);
    }
}
