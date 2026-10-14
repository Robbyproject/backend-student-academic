<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = DB::table('jadwal as j')
            ->join('kelas as k', 'j.kelas_id', '=', 'k.id')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'j.id',
                'j.kelas_id',
                'j.hari',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.ruangan',
                'k.nama_kelas',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'd.nama as nama_dosen'
            )
            ->orderBy('j.hari')
            ->orderBy('j.jam_mulai')
            ->get();

        return response()->json($schedules);
    }

        /**
     * Membuat jadwal baru.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'kelas_id' => 'required|integer|exists:kelas,id',
            'hari' => 'required|string|max:20',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'ruangan' => 'nullable|string|max:100',
        ]);

        $this->checkScheduleConflict($data);

        $id = DB::table('jadwal')->insertGetId([
            'kelas_id' => $data['kelas_id'],
            'hari' => $data['hari'],
            'jam_mulai' => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'],
            'ruangan' => $data['ruangan'] ?? null,
        ]);

        return response()->json([
            'message' => 'Jadwal berhasil ditambahkan.',
            'id' => $id,
        ], 201);
    }

    /**
     * Mengubah jadwal yang sudah ada.
     */
    public function update(Request $request, $id)
    {
        $existingSchedule = DB::table('jadwal')
            ->where('id', $id)
            ->first();

        if (!$existingSchedule) {
            return response()->json([
                'message' => 'Jadwal tidak ditemukan.',
            ], 404);
        }

        $data = $request->validate([
            'kelas_id' => 'required|integer|exists:kelas,id',
            'hari' => 'required|string|max:20',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'ruangan' => 'nullable|string|max:100',
        ]);

        // Abaikan jadwal yang sedang diedit.
        $this->checkScheduleConflict($data, (int) $id);

        DB::table('jadwal')
            ->where('id', $id)
            ->update([
                'kelas_id' => $data['kelas_id'],
                'hari' => $data['hari'],
                'jam_mulai' => $data['jam_mulai'],
                'jam_selesai' => $data['jam_selesai'],
                'ruangan' => $data['ruangan'] ?? null,
            ]);

        return response()->json([
            'message' => 'Jadwal berhasil diperbarui.',
        ]);
    }

    /**
     * Menghapus jadwal.
     */
    public function destroy($id)
    {
        $schedule = DB::table('jadwal')
            ->where('id', $id)
            ->first();

        if (!$schedule) {
            return response()->json([
                'message' => 'Jadwal tidak ditemukan.',
            ], 404);
        }

        DB::table('jadwal')
            ->where('id', $id)
            ->delete();

        return response()->json([
            'message' => 'Jadwal berhasil dihapus.',
        ]);
    }

    /**
     * Memeriksa bentrok waktu, kelas, dosen, dan ruangan.
     */
    private function checkScheduleConflict(
        array $data,
        ?int $excludeId = null
    ): void {
        $kelas = DB::table('kelas')
            ->where('id', $data['kelas_id'])
            ->first();

        if (!$kelas) {
            throw ValidationException::withMessages([
                'kelas_id' => ['Kelas tidak ditemukan.'],
            ]);
        }

        // Cari jadwal pada hari dan waktu yang bertumpang tindih.
        $query = DB::table('jadwal as j')
            ->join('kelas as k', 'j.kelas_id', '=', 'k.id')
            ->whereRaw(
                'LOWER(TRIM(j.hari)) = ?',
                [strtolower(trim($data['hari']))]
            )
            ->where('j.jam_mulai', '<', $data['jam_selesai'])
            ->where('j.jam_selesai', '>', $data['jam_mulai']);

        // Saat update, kecualikan jadwal yang sedang diedit.
        if ($excludeId !== null) {
            $query->where('j.id', '!=', $excludeId);
        }

        $existingSchedules = $query->select(
            'j.id',
            'j.kelas_id',
            'j.ruangan',
            'k.dosen_id'
        )->get();

        $errors = [];

        foreach ($existingSchedules as $schedule) {
            // 1. Kelas yang sama tidak boleh memiliki jadwal bersamaan.
            if ((int) $schedule->kelas_id === (int) $data['kelas_id']) {
                $errors['kelas_id'] =
                    'Kelas sudah memiliki jadwal pada waktu tersebut.';
            }

            // 2. Dosen yang sama tidak boleh mengajar bersamaan.
            if (
                $kelas->dosen_id !== null &&
                $schedule->dosen_id !== null &&
                (int) $schedule->dosen_id === (int) $kelas->dosen_id
            ) {
                $errors['dosen_id'] =
                    'Dosen sudah memiliki jadwal pada waktu tersebut.';
            }

            // 3. Ruangan yang sama tidak boleh dipakai bersamaan.
            $newRoom = trim((string) ($data['ruangan'] ?? ''));
            $existingRoom = trim((string) ($schedule->ruangan ?? ''));

            if (
                $newRoom !== '' &&
                $existingRoom !== '' &&
                strcasecmp($newRoom, $existingRoom) === 0
            ) {
                $errors['ruangan'] =
                    'Ruangan sudah digunakan pada waktu tersebut.';
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}