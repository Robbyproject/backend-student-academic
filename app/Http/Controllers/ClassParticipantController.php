<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassParticipantController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $participants = DB::table('tb_peserta_kelas as p')
            ->join('tb_kelas as k', 'p.kelas_id', '=', 'k.id')
            ->join('tb_mahasiswa as m', 'p.mahasiswa_id', '=', 'm.id')
            ->join('tb_matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('tb_dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select('p.id', 'k.id as kelas_id', 'k.nama_kelas', 'mk.kode_matkul', 'mk.nama_matkul', 'm.nim', 'm.nama as nama_mahasiswa', 'p.created_at')
            ->orderBy('k.nama_kelas')
            ->orderBy('m.nama');

        if ($user->role === 'mahasiswa') {
            $participants->where('m.user_id', $user->id);
        } elseif ($user->role === 'dosen') {
            $participants->where('d.user_id', $user->id);
        } elseif ($user->role !== 'admin') {
            abort(403, 'Role tidak memiliki akses ke data peserta kelas.');
        }

        return response()->json($participants->get());
    }
}