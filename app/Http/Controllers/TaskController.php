<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    // Menampilkan daftar tugas untuk Mahasiswa (berdasarkan kelas yang diikuti di peserta_kelas)
    public function indexForMahasiswa(int $mahasiswaId)
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
            ->join('peserta_kelas as pk', 'k.id', '=', 'pk.kelas_id')
            ->where('pk.mahasiswa_id', $mahasiswaId)
            ->whereDate('t.deadline', '>=', now()->toDateString())
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

    // Menampilkan daftar tugas (otomatis mendeteksi role: Admin/Dosen melihat semua, Mahasiswa melihat sesuai kelas peserta)
    public function index(Request $request)
    {
        $user = $request->user();

        // Jika yang akses adalah mahasiswa (berdasarkan token Sanctum)
        if ($user && $user->role === 'mahasiswa') {
            $mahasiswa = DB::table('mahasiswa')
                ->where('user_id', $user->id)
                ->first();

            if (!$mahasiswa) {
                return response()->json([], 200);
            }

            $tasks = DB::table('tugas as t')
                ->join('kelas as k', 't.kelas_id', '=', 'k.id')
                ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
                ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
                ->join('peserta_kelas as pk', 'k.id', '=', 'pk.kelas_id')
                ->where('pk.mahasiswa_id', $mahasiswa->id)
                ->whereDate('t.deadline', '>=', now()->toDateString())
                ->whereNotExists(function ($query) use ($mahasiswa) {
                    $query->select(DB::raw(1))
                        ->from('hasil_tugas as ht')
                        ->whereColumn('ht.tugas_id', 't.id')
                        ->where('ht.mahasiswa_id', $mahasiswa->id);
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

        // Untuk Admin / Dosen / Default (menampilkan semua riwayat tugas)
        $tasks = DB::table('tugas')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    // Menyimpan tugas baru dari Dosen
    public function store(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'kelas_id' => 'required|integer|exists:kelas,id',
            'deskripsi' => 'nullable|string',
            'deadline' => 'nullable|date',
            'file' => 'nullable|file|max:10240', // Maksimal 10MB
        ]);

        $kelasId = $request->kelas_id;

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('tasks', 'public');
        }

        $taskId = DB::table('tugas')->insertGetId([
            'judul' => $request->judul,
            'kelas_id' => $kelasId,
            'deskripsi' => $request->deskripsi ?? '-',
            'deadline' => $request->deadline ?? now()->addDays(7),
            'file_attachment_path' => $filePath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tugas berhasil dibuat',
            'task_id' => $taskId
        ], 201);
    }

    // Memperbarui tugas yang sudah ada
    public function update(Request $request, int $id)
    {
        $task = DB::table('tugas')->where('id', $id)->first();

        if (!$task) {
            return response()->json(['message' => 'Tugas tidak ditemukan.'], 404);
        }

        $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'deadline' => 'nullable|date',
            'file' => 'nullable|file|max:10240',
        ]);

        $filePath = $task->file_attachment_path;
        if ($request->hasFile('file')) {
            // Hapus file lama jika ada
            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $request->file('file')->store('tasks', 'public');
        }

        DB::table('tugas')->where('id', $id)->update([
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi ?? '-',
            'deadline' => $request->deadline ?? $task->deadline,
            'file_attachment_path' => $filePath,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tugas berhasil diperbarui',
        ]);
    }

    // Menghapus tugas
    public function destroy(int $id)
    {
        $task = DB::table('tugas')->where('id', $id)->first();

        if (!$task) {
            return response()->json(['message' => 'Tugas tidak ditemukan.'], 404);
        }

        if ($task->file_attachment_path && Storage::disk('public')->exists($task->file_attachment_path)) {
            Storage::disk('public')->delete($task->file_attachment_path);
        }

        DB::table('tugas')->where('id', $id)->delete();

        return response()->json([
            'message' => 'Tugas berhasil dihapus'
        ]);
    }

    // Mengambil daftar jurusan yang hanya diajar oleh dosen yang sedang login
    public function getDepartmentsByLecturer(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'dosen') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Cari ID dosen berdasarkan user_id
        $dosen = DB::table('dosen')->where('user_id', $user->id)->first();

        if (!$dosen) {
            return response()->json(['message' => 'Profil dosen tidak ditemukan.'], 404);
        }

        // Ambil jurusan unik dari mata kuliah pada kelas yang diampu oleh dosen ini
        $departments = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('jurusan as j', 'mk.jurusan_id', '=', 'j.id')
            ->where('k.dosen_id', $dosen->id)
            ->select('j.id', 'j.kode_jurusan', 'j.nama_jurusan')
            ->distinct()
            ->orderBy('j.nama_jurusan')
            ->get();

        return response()->json($departments);
    }

    // Mengambil daftar kelas berdasarkan jurusan khusus untuk dosen yang sedang login
    public function getClassesByJurusanAndLecturer(Request $request, int $jurusanId)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'dosen') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dosen = DB::table('dosen')->where('user_id', $user->id)->first();

        if (!$dosen) {
            return response()->json([], 404);
        }

        $classes = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->where('mk.jurusan_id', $jurusanId)
            ->where('k.dosen_id', $dosen->id)
            ->select('k.id', 'k.nama_kelas', 'mk.nama_matkul')
            ->orderBy('k.nama_kelas')
            ->get();

        return response()->json($classes);
    }
}