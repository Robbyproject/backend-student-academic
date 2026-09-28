<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademicClassController extends Controller
{
    // mengambil semua kelas
    public function index()
    {
        $classes = DB::table('tb_kelas as k')
            ->join('tb_matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('tb_dosen as d', 'k.dosen_id', '=', 'd.id')
            ->leftJoin('tb_jadwal as j', 'k.id', '=', 'j.kelas_id')
            ->select(
                'k.id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen',
                'j.hari',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.ruangan'
            )
            ->orderBy('mk.nama_matkul')
            ->orderBy('j.hari')
            ->orderBy('j.jam_mulai')
            ->get();

        $result = $classes
            ->groupBy('id')
            ->map(function ($items) {
                $first = $items->first();

                $schedules = $items
                    ->filter(function ($item) {
                        return $item->hari !== null;
                    })
                    ->map(function ($item) {
                        return $item->hari . ' ' .
                            substr($item->jam_mulai, 0, 5) . ' - ' .
                            substr($item->jam_selesai, 0, 5) .
                            ' (' . $item->ruangan . ')';
                    })
                    ->values()
                    ->toArray();

                return [
                    'id' => $first->id,
                    'nama_kelas' => $first->nama_kelas,
                    'tahun_ajaran' => $first->tahun_ajaran,
                    'kode_matkul' => $first->kode_matkul,
                    'nama_matkul' => $first->nama_matkul,
                    'sks' => $first->sks,
                    'nama_dosen' => $first->nama_dosen,
                    'schedules' => $schedules,
                ];
            })
            ->values();

        return response()->json($result);
    }

    //mengambil detail satu kelas
    public function show(string $id)
    {
        $class = DB::table('tb_kelas as k')
            ->join('tb_matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('tb_dosen as d', 'k.dosen_id', '=', 'd.id')
            ->where('k.id', $id)
            ->select(
                'k.id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen'
            )
            ->first();

        if (!$class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        $jumlahMahasiswa = DB::table('tb_peserta_kelas')
            ->where('kelas_id', $id)
            ->count();

        return response()->json([
            'id' => $class->id,
            'nama_kelas' => $class->nama_kelas,
            'tahun_ajaran' => $class->tahun_ajaran,
            'kode_matkul' => $class->kode_matkul,
            'nama_matkul' => $class->nama_matkul,
            'sks' => $class->sks,
            'nama_dosen' => $class->nama_dosen,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
        ]);
    }

    // AMBIL DOSEN SESUAI JURUSAN MATA KULIAH
    public function lecturersByCourse(string $matkulId)
    {
        $matkul = DB::table('tb_matkul')
            ->where('id', $matkulId)
            ->first();

        if (!$matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan'
            ], 404);
        }

        $dosen = DB::table('tb_dosen')
            ->where('jurusan_id', $matkul->jurusan_id)
            ->select(
                'id',
                'nama',
                'nidn'
            )
            ->orderBy('nama')
            ->get();

        return response()->json($dosen);
    }

    // BUAT KELAS BARU
    public function store(Request $request)
    {
        $validated = $request->validate([
            'matkul_id' => ['required', 'uuid'],
            'dosen_id' => ['required', 'uuid'],
            'nama_kelas' => ['required', 'string', 'max:255'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
        ]);

        // Cari mata kuliah
        $matkul = DB::table('tb_matkul')
            ->where('id', $validated['matkul_id'])
            ->first();

        if (!$matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan'
            ], 404);
        }

        // Cari dosen
        $dosen = DB::table('tb_dosen')
            ->where('id', $validated['dosen_id'])
            ->first();

        if (!$dosen) {
            return response()->json([
                'message' => 'Dosen tidak ditemukan'
            ], 404);
        }

        // Pastikan dosen dan mata kuliah berasal dari jurusan yang sama
        if ($dosen->jurusan_id !== $matkul->jurusan_id) {
            return response()->json([
                'message' => 'Dosen tidak berasal dari jurusan mata kuliah tersebut'
            ], 422);
        }

        $id = (string) Str::uuid();

        DB::table('tb_kelas')->insert([
            'id' => $id,
            'matkul_id' => $validated['matkul_id'],
            'dosen_id' => $validated['dosen_id'],
            'nama_kelas' => $validated['nama_kelas'],
            'tahun_ajaran' => $validated['tahun_ajaran'],
        ]);

        return response()->json([
            'message' => 'Kelas berhasil dibuat',
            'data' => [
                'id' => $id,
                'nama_kelas' => $validated['nama_kelas'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'matkul_id' => $validated['matkul_id'],
                'dosen_id' => $validated['dosen_id'],
            ]
        ], 201);
    }
}