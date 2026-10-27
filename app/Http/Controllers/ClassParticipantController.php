<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassParticipantController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $participants = DB::table('peserta_kelas as p')
            ->join('kelas as k', 'p.kelas_id', '=', 'k.id')
            ->join('mahasiswa as m', 'p.mahasiswa_id', '=', 'm.id')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'p.id',
                'k.id as kelas_id',
                'k.nama_kelas',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'm.nim',
                'm.nama as nama_mahasiswa',
                'p.created_at'
            )
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

    /**
     * Menambahkan mahasiswa ke dalam kelas (Admin).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mahasiswa_id' => ['required', 'integer', 'exists:mahasiswa,id'],
        ]);

        $exists = DB::table('peserta_kelas')
            ->where('kelas_id', $validated['kelas_id'])
            ->where('mahasiswa_id', $validated['mahasiswa_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'mahasiswa_id' => ['Mahasiswa sudah terdaftar di kelas ini.'],
            ]);
        }

        DB::table('peserta_kelas')->insert([
            'kelas_id' => $validated['kelas_id'],
            'mahasiswa_id' => $validated['mahasiswa_id'],
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Mahasiswa berhasil dimasukkan ke kelas.',
        ], 201);
    }

    /**
     * Mengeluarkan mahasiswa dari kelas (Admin).
     */
    public function destroy(int $id)
    {
        $deleted = DB::table('peserta_kelas')->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Data peserta kelas tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Mahasiswa berhasil dikeluarkan dari kelas.',
        ]);
    }
}