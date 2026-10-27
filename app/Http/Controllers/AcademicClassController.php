<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicClassController extends Controller
{
    /**
     * Daftar kelas dengan pagination.
     * Admin: semua kelas.
     * Mahasiswa: kelas sesuai jurusan.
     * Dosen: kelas yang diajar.
     */
    public function index(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();

        if (
            $user &&
            ! in_array($user->role, ['admin', 'mahasiswa', 'dosen'], true)
        ) {
            abort(403, 'Role tidak memiliki akses ke kelas akademik.');
        }

        $perPage = min(
            max((int) $request->query('per_page', 10), 1),
            100
        );

        $query = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->select(
                'k.id',
                'k.matkul_id',
                'k.dosen_id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'mk.jurusan_id',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen'
            );

        if ($user && $user->role === 'mahasiswa') {
            $mahasiswa = DB::table('mahasiswa')
                ->where('user_id', $user->id)
                ->first(['id', 'jurusan_id']);

            if (! $mahasiswa) {
                return response()->json([
                    'message' => 'Profil mahasiswa tidak ditemukan.',
                ], 404);
            }

            $query->where('mk.jurusan_id', $mahasiswa->jurusan_id);
        }

        if ($user && $user->role === 'dosen') {
            $dosen = DB::table('dosen')
                ->where('user_id', $user->id)
                ->first(['id']);

            if (! $dosen) {
                return response()->json([
                    'message' => 'Profil dosen tidak ditemukan.',
                ], 404);
            }

            $query->where('k.dosen_id', $dosen->id);
        }

        $paginator = $query
            ->orderBy('mk.nama_matkul')
            ->orderBy('k.nama_kelas')
            ->orderBy('k.id')
            ->paginate($perPage)
            ->withQueryString();

        $classIds = $paginator->getCollection()
            ->pluck('id')
            ->all();

        $schedulesByClass = collect();

        if (! empty($classIds)) {
            $schedulesByClass = DB::table('jadwal')
                ->whereIn('kelas_id', $classIds)
                ->orderByRaw("
                    CASE LOWER(TRIM(hari))
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
                ->orderBy('jam_mulai')
                ->get()
                ->groupBy('kelas_id');
        }

        $paginator->getCollection()->transform(
            function ($class) use ($schedulesByClass) {
                $schedules = $schedulesByClass
                    ->get($class->id, collect())
                    ->map(function ($schedule) {
                        return $schedule->hari . ' ' .
                            substr((string) $schedule->jam_mulai, 0, 5) . ' - ' .
                            substr((string) $schedule->jam_selesai, 0, 5) .
                            ' (' . ($schedule->ruangan ?? '-') . ')';
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $class->id,
                    'matkul_id' => $class->matkul_id,
                    'dosen_id' => $class->dosen_id,
                    'jurusan_id' => $class->jurusan_id,
                    'nama_kelas' => $class->nama_kelas,
                    'tahun_ajaran' => $class->tahun_ajaran,
                    'term' => $class->term,
                    'kode_matkul' => $class->kode_matkul,
                    'nama_matkul' => $class->nama_matkul,
                    'sks' => $class->sks,
                    'nama_dosen' => $class->nama_dosen,
                    'schedules' => $schedules,
                ];
            }
        );

        return response()->json($paginator);
    }

    /**
     * Detail satu kelas beserta jadwal dan jumlah mahasiswa.
     */
    public function show(string $id)
    {
        $class = DB::table('kelas as k')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->join('dosen as d', 'k.dosen_id', '=', 'd.id')
            ->where('k.id', $id)
            ->select(
                'k.id',
                'k.matkul_id',
                'k.dosen_id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'mk.jurusan_id',
                'mk.kode_matkul',
                'mk.nama_matkul',
                'mk.sks',
                'd.nama as nama_dosen'
            )
            ->first();

        if (! $class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan.',
            ], 404);
        }

        $jumlahMahasiswa = DB::table('peserta_kelas')
            ->where('kelas_id', $id)
            ->count();

        $schedules = DB::table('jadwal')
            ->where('kelas_id', $id)
            ->orderBy('jam_mulai')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'hari' => $schedule->hari,
                    'jam_mulai' => $schedule->jam_mulai,
                    'jam_selesai' => $schedule->jam_selesai,
                    'ruangan' => $schedule->ruangan,
                ];
            });

        return response()->json([
            'id' => $class->id,
            'matkul_id' => $class->matkul_id,
            'dosen_id' => $class->dosen_id,
            'jurusan_id' => $class->jurusan_id,
            'nama_kelas' => $class->nama_kelas,
            'tahun_ajaran' => $class->tahun_ajaran,
            'term' => $class->term,
            'kode_matkul' => $class->kode_matkul,
            'nama_matkul' => $class->nama_matkul,
            'sks' => $class->sks,
            'nama_dosen' => $class->nama_dosen,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Dosen yang sesuai dengan jurusan mata kuliah.
     */
    public function lecturersByCourse(string $matkulId)
    {
        $matkul = DB::table('matkul')
            ->where('id', $matkulId)
            ->first();

        if (! $matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan.',
            ], 404);
        }

        $dosen = DB::table('dosen')
            ->where('jurusan_id', $matkul->jurusan_id)
            ->select('id', 'nama', 'nidn')
            ->orderBy('nama')
            ->get();

        return response()->json($dosen);
    }

    /**
     * Membuat kelas dan jadwal sekaligus.
     *
     * Jadwal ditolak jika:
     * - Ruangan sama dan waktu bertabrakan.
     * - Dosen sama dan waktu bertabrakan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'matkul_id' => ['required', 'integer', 'min:1'],
            'dosen_id' => ['required', 'integer', 'min:1'],
            'nama_kelas' => ['required', 'string', 'max:255'],
            'tahun_ajaran' => [
                'required',
                'regex:/^\d{4}\/\d{4}$/',
            ],
            'term' => ['required', 'in:ganjil,genap'],
            'hari' => [
                'required',
                'string',
                'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            ],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => [
                'required',
                'date_format:H:i',
                'after:jam_mulai',
            ],
            'ruangan' => ['required', 'string', 'max:255'],
        ]);

        [$yearStart, $yearEnd] = explode(
            '/',
            $validated['tahun_ajaran']
        );

        if ((int) $yearEnd !== (int) $yearStart + 1) {
            throw ValidationException::withMessages([
                'tahun_ajaran' =>
                    'Tahun ajaran harus terdiri dari dua tahun berurutan, misalnya 2026/2027.',
            ]);
        }

        $matkul = DB::table('matkul')
            ->where('id', $validated['matkul_id'])
            ->first();

        if (! $matkul) {
            return response()->json([
                'message' => 'Mata kuliah tidak ditemukan.',
            ], 404);
        }

        $dosen = DB::table('dosen')
            ->where('id', $validated['dosen_id'])
            ->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Dosen tidak ditemukan.',
            ], 404);
        }

        if ((int) $dosen->jurusan_id !== (int) $matkul->jurusan_id) {
            throw ValidationException::withMessages([
                'dosen_id' =>
                    'Dosen tidak berasal dari jurusan mata kuliah tersebut.',
            ]);
        }

        // Simpan kelas dan jadwal dalam satu transaksi.
        // Jika terjadi error, keduanya dibatalkan.
        $result = DB::transaction(function () use ($validated) {
            $this->checkScheduleConflict($validated);

            $classId = DB::table('kelas')->insertGetId([
                'matkul_id' => $validated['matkul_id'],
                'dosen_id' => $validated['dosen_id'],
                'nama_kelas' => trim($validated['nama_kelas']),
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'term' => $validated['term'],
            ]);

            DB::table('jadwal')->insert([
                'kelas_id' => $classId,
                'hari' => trim($validated['hari']),
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'ruangan' => trim($validated['ruangan']),
                'created_at' => now(),
            ]);

            return [
                'id' => $classId,
                'matkul_id' => (int) $validated['matkul_id'],
                'dosen_id' => (int) $validated['dosen_id'],
                'nama_kelas' => trim($validated['nama_kelas']),
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'term' => $validated['term'],
            ];
        });

        return response()->json([
            'message' => 'Kelas dan jadwal berhasil dibuat.',
            'kelas_id' => $result['id'],
            'data' => $result,
        ], 201);
    }

    /**
     * Memeriksa bentrok jadwal pada hari dan rentang waktu yang beririsan.
     */
    private function checkScheduleConflict(array $data): void
    {
        $hari = mb_strtolower(trim($data['hari']));
        $ruangan = mb_strtolower(trim($data['ruangan']));

        $conflicts = DB::table('jadwal as j')
            ->join('kelas as k', 'j.kelas_id', '=', 'k.id')
            ->join('matkul as mk', 'k.matkul_id', '=', 'mk.id')
            ->whereRaw('LOWER(TRIM(j.hari)) = ?', [$hari])
            ->whereRaw('CAST(j.jam_mulai AS TIME) < CAST(? AS TIME)', [$data['jam_selesai']])
            ->whereRaw('CAST(j.jam_selesai AS TIME) > CAST(? AS TIME)', [$data['jam_mulai']])
            ->where(function ($query) use ($data, $ruangan) {
                $query
                    ->whereRaw(
                        'LOWER(TRIM(j.ruangan)) = ?',
                        [$ruangan]
                    )
                    ->orWhere('k.dosen_id', $data['dosen_id']);
            })
            ->select(
                'j.hari',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.ruangan',
                'k.id as kelas_id',
                'k.nama_kelas',
                'k.dosen_id',
                'mk.nama_matkul'
            )
            ->get();

        if ($conflicts->isEmpty()) {
            return;
        }

        $conflict = $conflicts->first();
        $reasons = [];

        $sameRoom = mb_strtolower(trim($conflict->ruangan ?? '')) === $ruangan;
        $sameLecturer = (int) $conflict->dosen_id === (int) $data['dosen_id'];

        if ($sameRoom) {
            $reasons[] = "ruangan {$conflict->ruangan}";
        }

        if ($sameLecturer) {
            $reasons[] = 'dosen yang sama';
        }

        throw ValidationException::withMessages([
            'jadwal' => [
                'Jadwal bentrok dengan kelas '
                . $conflict->nama_matkul . ' (' . $conflict->nama_kelas . ')'
                . ' pada hari '
                . $conflict->hari
                . ', pukul '
                . substr((string) $conflict->jam_mulai, 0, 5)
                . '–'
                . substr((string) $conflict->jam_selesai, 0, 5)
                . '. Bentrok pada '
                . implode(' dan ', $reasons)
                . '. Silakan pilih jadwal lain.',
            ],
        ]);
    }
}