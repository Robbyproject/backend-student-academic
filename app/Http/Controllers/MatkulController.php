<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class MatkulController extends Controller
{
    public function index()
    {
        $matkul = DB::table('matkul')
            ->select(
                'id',
                'kode_matkul',
                'nama_matkul',
                'sks',
                'jurusan_id'
            )
            ->orderBy('nama_matkul')
            ->get();

        return response()->json($matkul);
    }
}