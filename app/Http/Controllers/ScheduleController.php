<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = DB::table('jadwal as j')
            ->join('kelas as k', 'j.kelas_id', '=', 'k.id')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'j.id',
                'j.hari',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.ruangan',
                'k.nama_kelas',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'd.nama as nama_dosen'
            )
            ->orderBy('j.hari')
            ->orderBy('j.jam_mulai')
            ->get();

        return response()->json($schedules);
    }
}