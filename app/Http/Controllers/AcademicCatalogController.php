<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class AcademicCatalogController extends Controller
{
    public function departments()
    {
        $departments = DB::table('tb_jurusan')
            ->select('id', 'kode_jurusan', 'nama_jurusan')
            ->orderBy('nama_jurusan')
            ->get();

        return response()->json($departments);
    }

    public function lecturers()
    {
        $lecturers = DB::table('tb_dosen as d')
            ->join('tb_jurusan as j', 'd.jurusan_id', '=', 'j.id')
            ->select('d.id', 'd.nidn', 'd.nama', 'j.kode_jurusan', 'j.nama_jurusan')
            ->orderBy('d.nama')
            ->get();

        return response()->json($lecturers);
    }

    public function courses()
    {
        $courses = DB::table('tb_matkul as m')
            ->join('tb_jurusan as j', 'm.jurusan_id', '=', 'j.id')
            ->select('m.id', 'm.kode_matkul', 'm.nama_matkul', 'm.sks', 'j.kode_jurusan', 'j.nama_jurusan')
            ->orderBy('m.nama_matkul')
            ->get();

        return response()->json($courses);
    }
}