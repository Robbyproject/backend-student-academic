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
            ->select(
                'h.id',
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

    public function store(Request $request)
    {
    $request->validate([
        'tugas_id' => 'required|exists:tugas,id',
        'mahasiswa_id' => 'required|exists:mahasiswa,id',
        'file' => 'required|file|max:10240', // Maksimal 10MB
    ]);

    $task = DB::table('tugas')->where('id', $request->tugas_id)->first();

    // Validasi Deadline (Cek apakah hari ini melebihi tanggal deadline)
    if (now()->toDateString() > $task->deadline) {
        return response()->json([
            'message' => 'Deadline pengumpulan tugas sudah terlewat.'
        ], 400);
    }

    // Cek apakah sudah pernah mengumpulkan
    $existing = DB::table('hasil_tugas')
        ->where('tugas_id', $request->tugas_id)
        ->where('mahasiswa_id', $request->mahasiswa_id)
        ->first();

    if ($existing) {
        return response()->json([
            'message' => 'Anda sudah mengumpulkan tugas ini.'
        ], 400);
    }

    $filePath = null;
    if ($request->hasFile('file')) {
        $filePath = $request->file('file')->store('submissions', 'public');
    }

    DB::table('hasil_tugas')->insert([
        'tugas_id' => $request->tugas_id,
        'mahasiswa_id' => $request->mahasiswa_id,
        'file_submission_path' => $filePath,
        'submitted_at' => now(), // Menyimpan waktu standar server/UTC
    ]);

    return response()->json([
        'message' => 'Tugas berhasil dikumpulkan!',
    ], 201);
}
}