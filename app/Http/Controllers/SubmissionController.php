<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $submissions = DB::table('tb_hasil_tugas as h')
            ->join('tb_tugas as t', 'h.tugas_id', '=', 't.id')
            ->join('tb_kelas as k', 't.kelas_id', '=', 'k.id')
            ->join('tb_matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('tb_mahasiswa as m', 'h.mahasiswa_id', '=', 'm.id')
            ->join('tb_dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select('h.id', 't.judul as judul_tugas', 'k.nama_kelas', 'mk.kode_matkul', 'mk.nama_matkul', 'm.nim', 'm.nama as nama_mahasiswa', 'h.file_submission_path', 'h.nilai', 'h.catatan_dosen', 'h.submitted_at')
            ->orderByDesc('h.submitted_at');

        if ($user->role === 'mahasiswa') {
            $submissions->where('m.user_id', $user->id);
        } elseif ($user->role === 'dosen') {
            $submissions->where('d.user_id', $user->id);
        } elseif ($user->role !== 'admin') {
            abort(403, 'Role tidak memiliki akses ke hasil tugas.');
        }

        return response()->json($submissions->get());
    }
}