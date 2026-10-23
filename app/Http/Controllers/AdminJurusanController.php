<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminJurusanController extends Controller
{
    // Mengambil daftar jurusan
    public function index()
    {
        $jurusan = DB::table('jurusan')
            ->select('id', 'kode_jurusan', 'nama_jurusan')
            ->orderBy('nama_jurusan')
            ->get();

        return response()->json($jurusan);
    }

    // Menambahkan jurusan baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_jurusan' => [
                'required',
                'string',
                'max:50',
                'unique:jurusan,kode_jurusan',
            ],
            'nama_jurusan' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $kode = strtoupper(trim($validated['kode_jurusan']));
        $nama = trim($validated['nama_jurusan']);

        if (DB::table('jurusan')->where('kode_jurusan', $kode)->exists()) {
            throw ValidationException::withMessages([
                'kode_jurusan' => 'Kode jurusan sudah digunakan.',
            ]);
        }

        $id = DB::table('jurusan')->insertGetId([
            'kode_jurusan' => $kode,
            'nama_jurusan' => $nama,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Jurusan berhasil ditambahkan.',
            'data' => [
                'id' => $id,
                'kode_jurusan' => $kode,
                'nama_jurusan' => $nama,
            ],
        ], 201);
    }

    // Memperbarui jurusan
    public function update(Request $request, string $id)
    {
        $jurusan = DB::table('jurusan')->where('id', $id)->first();

        if (!$jurusan) {
            return response()->json([
                'message' => 'Jurusan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'kode_jurusan' => [
                'required',
                'string',
                'max:50',
                Rule::unique('jurusan', 'kode_jurusan')->ignore($id, 'id'),
            ],
            'nama_jurusan' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $kode = strtoupper(trim($validated['kode_jurusan']));

        DB::table('jurusan')
            ->where('id', $id)
            ->update([
                'kode_jurusan' => $kode,
                'nama_jurusan' => trim($validated['nama_jurusan']),
            ]);

        return response()->json([
            'message' => 'Jurusan berhasil diperbarui.',
        ]);
    }

    // Menghapus jurusan
    public function destroy(string $id)
    {
        $jurusan = DB::table('jurusan')->where('id', $id)->first();

        if (!$jurusan) {
            return response()->json([
                'message' => 'Jurusan tidak ditemukan.',
            ], 404);
        }

        try {
            DB::table('jurusan')->where('id', $id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' =>
                    'Jurusan tidak bisa dihapus karena masih digunakan oleh data dosen atau mata kuliah.',
            ], 409);
        }

        return response()->json([
            'message' => 'Jurusan berhasil dihapus.',
        ]);
    }
}
