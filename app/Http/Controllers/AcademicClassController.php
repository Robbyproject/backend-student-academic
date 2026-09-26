<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class AcademicClassController extends Controller
{
    public function index()
    {
        $classes = DB::table('tb_kelas as k')
            ->join('tb_matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('tb_dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'k.id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen'
            )
            ->orderBy('mk.nama_matkul')
            ->get();

        return response()->json($classes);
    }
}