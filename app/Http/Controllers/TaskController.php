<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = DB::table('tb_tugas as t')
            ->join('tb_kelas as k', 't.kelas_id', '=', 'k.id')
            ->join('tb_matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->select(
                't.id',
                't.judul',
                't.deskripsi',
                't.deadline',
                'mk.kode_matkul',
                'mk.nama_matkul'
            )
            ->orderBy('t.deadline', 'asc')
            ->get();

        return response()->json($tasks);
    }
}