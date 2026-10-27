<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();

        $query = DB::table('jadwal as j')
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
            );

        // Mahasiswa hanya melihat jadwal dari jurusannya.
        if ($user && $user->role === 'mahasiswa') {
            $mahasiswa = DB::table('mahasiswa')
                ->where('user_id', $user->id)
                ->first(['jurusan_id']);

            if (! $mahasiswa) {
                return response()->json([
                    'message' => 'Profil mahasiswa tidak ditemukan.',
                ], 404);
            }

            $query->where('mk.jurusan_id', $mahasiswa->jurusan_id);
        } elseif ($user && $user->role === 'dosen') {
            // Dosen hanya melihat jadwal kelas yang diajarnya.
            $dosen = DB::table('dosen')
                ->where('user_id', $user->id)
                ->first(['id']);

            if (! $dosen) {
                return response()->json([
                    'message' => 'Profil dosen tidak ditemukan.',
                ], 404);
            }

            $query->where('k.dosen_id', $dosen->id);
        } elseif ($user && $user->role !== 'admin') {
            return response()->json([
                'message' => 'Role tidak memiliki akses ke jadwal.',
            ], 403);
        }

        $schedules = $query
            ->orderByRaw("
                CASE LOWER(TRIM(j.hari))
                    WHEN 'senin' THEN 1
                    WHEN 'selasa' THEN 2
                    WHEN 'rabu' THEN 3
                    WHEN 'kamis' THEN 4
                    WHEN 'jumat' THEN 5
                    WHEN 'sabtu' THEN 6
                    WHEN 'minggu' THEN 7
                    ELSE 8
                END
            ")
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
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'hari' => ['required', 'string', 'max:20'],
            'jam_mulai' => ['required', 'string'],
            'jam_selesai' => ['required', 'string'],
            'ruangan' => ['nullable', 'string', 'max:100'],
        ]);

        $jamMulaiTs = strtotime(trim($data['jam_mulai']));
        $jamSelesaiTs = strtotime(trim($data['jam_selesai']));

        if ($jamMulaiTs === false || $jamSelesaiTs === false || $jamSelesaiTs <= $jamMulaiTs) {
            throw ValidationException::withMessages([
                'jam_selesai' => ['Jam selesai harus lebih besar dari jam mulai.'],
            ]);
        }

        $data['jam_mulai'] = date('H:i', $jamMulaiTs);
        $data['jam_selesai'] = date('H:i', $jamSelesaiTs);
        $data['hari'] = trim($data['hari']);
        $data['ruangan'] = isset($data['ruangan']) ? trim($data['ruangan']) : null;

        $this->checkScheduleConflict($data);

        $id = DB::table('jadwal')->insertGetId([
            'kelas_id' => $data['kelas_id'],
            'hari' => $data['hari'],
            'jam_mulai' => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'],
            'ruangan' => $data['ruangan'],
            'created_at' => now(),
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

        if (! $existingSchedule) {
            return response()->json([
                'message' => 'Jadwal tidak ditemukan.',
            ], 404);
        }

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'hari' => ['required', 'string', 'max:20'],
            'jam_mulai' => ['required', 'string'],
            'jam_selesai' => ['required', 'string'],
            'ruangan' => ['nullable', 'string', 'max:100'],
        ]);

        $jamMulaiTs = strtotime(trim($data['jam_mulai']));
        $jamSelesaiTs = strtotime(trim($data['jam_selesai']));

        if ($jamMulaiTs === false || $jamSelesaiTs === false || $jamSelesaiTs <= $jamMulaiTs) {
            throw ValidationException::withMessages([
                'jam_selesai' => ['Jam selesai harus lebih besar dari jam mulai.'],
            ]);
        }

        $data['jam_mulai'] = date('H:i', $jamMulaiTs);
        $data['jam_selesai'] = date('H:i', $jamSelesaiTs);
        $data['hari'] = trim($data['hari']);
        $data['ruangan'] = isset($data['ruangan']) ? trim($data['ruangan']) : null;

        // Abaikan jadwal yang sedang diedit.
        $this->checkScheduleConflict($data, (int) $id);

        DB::table('jadwal')
            ->where('id', $id)
            ->update([
                'kelas_id' => $data['kelas_id'],
                'hari' => $data['hari'],
                'jam_mulai' => $data['jam_mulai'],
                'jam_selesai' => $data['jam_selesai'],
                'ruangan' => $data['ruangan'],
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

        if (! $schedule) {
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

        if (! $kelas) {
            throw ValidationException::withMessages([
                'kelas_id' => ['Kelas tidak ditemukan.'],
            ]);
        }

        // Cari jadwal pada hari dan waktu yang bertumpang tindih.
        $query = DB::table('jadwal as j')
            ->join('kelas as k', 'j.kelas_id', '=', 'k.id')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->whereRaw(
                'LOWER(TRIM(j.hari)) = ?',
                [mb_strtolower(trim($data['hari']))]
            )
            ->whereRaw('CAST(j.jam_mulai AS TIME) < CAST(? AS TIME)', [$data['jam_selesai']])
            ->whereRaw('CAST(j.jam_selesai AS TIME) > CAST(? AS TIME)', [$data['jam_mulai']]);

        // Saat update, kecualikan jadwal yang sedang diedit.
        if ($excludeId !== null) {
            $query->where('j.id', '!=', $excludeId);
        }

        $existingSchedules = $query->select(
            'j.id',
            'j.kelas_id',
            'j.hari',
            'j.jam_mulai',
            'j.jam_selesai',
            'j.ruangan',
            'k.nama_kelas',
            'k.dosen_id',
            'mk.nama_matkul'
        )->get();

        $errors = [];

        foreach ($existingSchedules as $schedule) {
            // 1. Kelas yang sama tidak boleh memiliki jadwal bersamaan.
            if ((int) $schedule->kelas_id === (int) $data['kelas_id']) {
                $errors['kelas_id'] =
                    'Kelas ini sudah memiliki jadwal pada hari dan waktu yang bertabrakan.';
            }

            // 2. Dosen yang sama tidak boleh mengajar bersamaan.
            if (
                $kelas->dosen_id !== null &&
                $schedule->dosen_id !== null &&
                (int) $schedule->dosen_id === (int) $kelas->dosen_id
            ) {
                $errors['dosen_id'] = sprintf(
                    'Dosen sudah memiliki jadwal mengajar kelas %s (%s) pada hari %s pukul %s–%s.',
                    $schedule->nama_matkul,
                    $schedule->nama_kelas,
                    $schedule->hari,
                    substr((string) $schedule->jam_mulai, 0, 5),
                    substr((string) $schedule->jam_selesai, 0, 5)
                );
            }

            // 3. Ruangan yang sama tidak boleh dipakai bersamaan.
            $newRoom = trim((string) ($data['ruangan'] ?? ''));
            $existingRoom = trim((string) ($schedule->ruangan ?? ''));

            if (
                $newRoom !== '' &&
                $existingRoom !== '' &&
                strcasecmp($newRoom, $existingRoom) === 0
            ) {
                $errors['ruangan'] = sprintf(
                    'Ruangan %s sudah digunakan oleh kelas %s (%s) pada hari %s pukul %s–%s.',
                    $schedule->ruangan,
                    $schedule->nama_matkul,
                    $schedule->nama_kelas,
                    $schedule->hari,
                    substr((string) $schedule->jam_mulai, 0, 5),
                    substr((string) $schedule->jam_selesai, 0, 5)
                );
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}