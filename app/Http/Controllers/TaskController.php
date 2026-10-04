<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index($mahasiswaId)
    {
        $mahasiswa = DB::table('mahasiswa')
            ->where('id', $mahasiswaId)
            ->first();

        if (!$mahasiswa) {
            return response()->json([
                'message' => 'Mahasiswa tidak ditemukan.'
            ], 404);
        }

        $tasks = DB::table('tugas as t')
            ->join('kelas as k', 't.kelas_id', '=', 'k.id')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            // hanya tugas dari jurusan mahasiswa
            ->where('mk.jurusan_id', $mahasiswa->jurusan_id)
            // deadline hari ini atau setelah hari ini
            ->whereDate('t.deadline', '>=', now()->toDateString())
            // jangan tampilkan tugas yang sudah dikumpulkan
            ->whereNotExists(function ($query) use ($mahasiswaId) {
                $query->select(DB::raw(1))
                    ->from('hasil_tugas as ht')
                    ->whereColumn('ht.tugas_id', 't.id')
                    ->where('ht.mahasiswa_id', $mahasiswaId);
            })

            ->select(
                't.id',
                't.judul',
                't.deskripsi',
                't.deadline',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'd.nama as nama_dosen'
            )

            ->orderBy('t.deadline', 'asc')
            ->get();

        return response()->json($tasks);
    }
}