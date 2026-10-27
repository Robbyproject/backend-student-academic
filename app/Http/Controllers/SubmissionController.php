<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    /**
     * Menampilkan daftar pengumpulan tugas.
     * Mahasiswa hanya melihat pengumpulan miliknya.
     * Dosen hanya melihat pengumpulan pada kelas yang diajarnya.
     * Admin dapat melihat semua pengumpulan.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $submissions = DB::table('hasil_tugas as h')
            ->join('tugas as t', 'h.tugas_id', '=', 't.id')
            ->join('kelas as k', 't.kelas_id', '=', 'k.id')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('mahasiswa as m', 'h.mahasiswa_id', '=', 'm.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'h.id',
                'h.tugas_id',
                't.judul as judul_tugas',
                'k.nama_kelas',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'm.nim',
                'm.nama as nama_mahasiswa',
                'h.file_submission_path',
                'h.nilai',
                'h.catatan_dosen',
                'h.submitted_at'
            )
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

    /**
     * Menyimpan pengumpulan tugas mahasiswa.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tugas_id' => ['required', 'integer', 'exists:tugas,id'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $user = $request->user();

        if (!$user || $user->role !== 'mahasiswa') {
            abort(403, 'Hanya mahasiswa yang dapat mengumpulkan tugas.');
        }

        // Ambil profil mahasiswa berdasarkan akun yang sedang login.
        $mahasiswa = DB::table('mahasiswa')
            ->where('user_id', $user->id)
            ->first();

        if (!$mahasiswa) {
            return response()->json([
                'message' => 'Profil mahasiswa tidak ditemukan.',
            ], 404);
        }

        $task = DB::table('tugas')
            ->where('id', $validated['tugas_id'])
            ->first();

        if (!$task) {
            return response()->json([
                'message' => 'Tugas tidak ditemukan.',
            ], 404);
        }

        // Periksa deadline beserta jamnya jika kolom deadline menyimpan waktu.
        if (now()->greaterThan(\Illuminate\Support\Carbon::parse($task->deadline))) {
            return response()->json([
                'message' => 'Deadline pengumpulan tugas sudah terlewat.',
            ], 422);
        }

        // Pastikan mahasiswa belum mengumpulkan tugas yang sama.
        $existing = DB::table('hasil_tugas')
            ->where('tugas_id', $validated['tugas_id'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'Anda sudah mengumpulkan tugas ini.',
            ], 422);
        }

        $filePath = $request->file('file')->store(
            'submissions',
            'public'
        );

        DB::table('hasil_tugas')->insert([
            'tugas_id' => $validated['tugas_id'],
            'mahasiswa_id' => $mahasiswa->id,
            'file_submission_path' => $filePath,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tugas berhasil dikumpulkan!',
        ], 201);
    }
}