<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $students = DB::table('mahasiswa as m')
            ->join('jurusan as j', 'm.jurusan_id', '=', 'j.id')
            ->select(
                'm.id',
                'm.nim',
                'm.nama',
                'm.angkatan',
                'j.kode_jurusan',
                'j.nama_jurusan'
            )
            ->orderBy('m.nama');

        if ($user->role === 'mahasiswa') {
            $students->where('m.user_id', $user->id);
        } elseif ($user->role === 'dosen') {
            $students->whereExists(function ($query) use ($user) {
                $query->selectRaw('1')
                    ->from('peserta_kelas as p')
                    ->join('kelas as k', 'p.kelas_id', '=', 'k.id')
                    ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
                    ->whereColumn('p.mahasiswa_id', 'm.id')
                    ->where('d.user_id', $user->id);
            });
        } elseif ($user->role !== 'admin') {
            abort(403, 'Role tidak memiliki akses ke data mahasiswa.');
        }

        return response()->json($students->get());
    }
}