<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAcademicController extends Controller
{
    public function data()
    {
        return response()->json([
            'jurusan' => DB::table('jurusan')
                ->select(
                    'id',
                    'kode_jurusan',
                    'nama_jurusan'
                )
                ->orderBy('nama_jurusan')
                ->get(),

            'dosen' => DB::table('dosen')
                ->select(
                    'id',
                    'nama',
                    'nidn',
                    'jurusan_id'
                )
                ->orderBy('nama')
                ->get(),

            'matkul' => DB::table('matkul')
                ->select(
                    'id',
                    'kode_matkul',
                    'nama_matkul',
                    'sks',
                    'jurusan_id'
                )
                ->orderBy('nama_matkul')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dosen_id' => 'required|integer|min:1',
            'matkul_id' => 'required|integer|min:1',
            'nama_kelas' => 'required|string|max:255',
            'tahun_ajaran' => 'required|string|max:20',

            'hari' => 'required|string|max:20',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'ruangan' => 'required|string|max:50',
        ]);

        $dosen = DB::table('dosen')
            ->where('id', $validated['dosen_id'])
            ->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Dosen tidak ditemukan.',
            ], 404);
        }

        $matkul = DB::table('matkul')
            ->where('id', $validated['matkul_id'])
            ->first();

        if (! $matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan.',
            ], 404);
        }

        // Pastikan dosen dan mata kuliah berasal
        // dari jurusan yang sama.
        if ((int) $dosen->jurusan_id !== (int) $matkul->jurusan_id) {
            return response()->json([
                'message' => 'Dosen dan mata kuliah harus berasal dari jurusan yang sama.',
            ], 422);
        }

        $kelasId = DB::transaction(function () use ($validated) {
            $kelasId = DB::table('kelas')->insertGetId([
                'matkul_id' => $validated['matkul_id'],
                'dosen_id' => $validated['dosen_id'],
                'nama_kelas' => $validated['nama_kelas'],
                'tahun_ajaran' => $validated['tahun_ajaran'],
            ]);

            DB::table('jadwal')->insert([
                'kelas_id' => $kelasId,
                'hari' => $validated['hari'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'ruangan' => $validated['ruangan'],
                'created_at' => now(),
            ]);

            return $kelasId;
        });

        return response()->json([
            'message' => 'Kelas dan jadwal berhasil dibuat.',
            'kelas_id' => $kelasId,
        ], 201);
    }
}
