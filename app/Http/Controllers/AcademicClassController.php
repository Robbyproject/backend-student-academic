<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

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
}