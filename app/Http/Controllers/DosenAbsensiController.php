<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Absensi manual untuk dosen.
 *
 * Daftar peserta otomatis mengikuti jurusan dosen
 * (jurusan mata kuliah). Dosen hanya dapat mengakses kelas miliknya sendiri.
 */
class DosenAbsensiController extends Controller
{
    /**
     * Daftar kelas yang diajar dosen yang sedang login,
     * dikelompokkan per jurusan dan semester (term).
     */
    public function classes(Request $request)
    {
        $dosenId = $this->authorizeDosen($request);

        $validated = $request->validate([
            'jurusan_id' => ['sometimes', 'integer', 'exists:jurusan,id'],
        ]);

        $query = DB::table('kelas as k')
            ->join('matkul as m', 'm.id', '=', 'k.matkul_id')
            ->join('jurusan as jur', 'jur.id', '=', 'm.jurusan_id')
            ->where('k.dosen_id', $dosenId)
            ->select(
                'k.id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'm.id as matkul_id',
                'm.kode_matkul',
                'm.nama_matkul',
                'm.jurusan_id',
                'jur.kode_jurusan',
                'jur.nama_jurusan'
            )
            ->orderBy('jur.nama_jurusan')
            ->orderBy('m.nama_matkul')
            ->orderBy('k.nama_kelas');

        if (isset($validated['jurusan_id'])) {
            $query->where('m.jurusan_id', $validated['jurusan_id']);
        }

        $classes = $query->get();

        if ($classes->isEmpty()) {
            return response()->json($classes);
        }

        $counts = DB::table('peserta_kelas')
            ->whereIn('kelas_id', $classes->pluck('id')->all())
            ->select('kelas_id', DB::raw('COUNT(*) as total'))
            ->groupBy('kelas_id')
            ->get();

        // Pluck di level collection, bukan query builder, agar
        // alias COUNT(*) tidak dianggap nama kolom tabel oleh PostgreSQL.
        $countsByClass = $counts->pluck('total', 'kelas_id');

        $classes = $classes->map(function ($class) use ($countsByClass) {
            $class->jumlah_peserta = (int) ($countsByClass[$class->id] ?? 0);

            return $class;
        });

        return response()->json($classes);
    }

    /**
     * Daftar peserta satu kelas (paginasi) lengkap dengan
     * status absensi pada tanggal tertentu.
     */
    public function participants(Request $request, int $kelasId)
    {
        $dosenId = $this->authorizeDosen($request);

        $validated = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $class = $this->getOwnedClass($dosenId, $kelasId);

        $perPage = $validated['per_page'] ?? 10;
        $tanggal = $validated['tanggal'];

        $absensiByStudent = DB::table('absensi')
            ->where('kelas_id', $kelasId)
            ->where('tanggal', $tanggal)
            ->pluck('status', 'mahasiswa_id');

        $paginator = DB::table('peserta_kelas as pk')
            ->join('mahasiswa as mhs', 'mhs.id', '=', 'pk.mahasiswa_id')
            ->where('pk.kelas_id', $kelasId)
            ->orderBy('mhs.nama')
            ->select('mhs.id', 'mhs.nim', 'mhs.nama', 'mhs.angkatan')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function ($student) use (
            $absensiByStudent,
            $class
        ) {
            $student->status = $absensiByStudent[$student->id] ?? null;
            $student->semester = $this->studentSemester($class, $student->angkatan);

            return $student;
        });

        $summary = $this->attendanceSummary($kelasId, $tanggal);

        return response()->json([
            ...$paginator->toArray(),
            'kelas' => [
                'id' => $class->id,
                'nama_kelas' => $class->nama_kelas,
                'tahun_ajaran' => $class->tahun_ajaran,
                'term' => $class->term,
                'semester_label' => $this->classSemesterLabel($class),
                'kode_matkul' => $class->kode_matkul,
                'nama_matkul' => $class->nama_matkul,
                'kode_jurusan' => $class->kode_jurusan,
                'nama_jurusan' => $class->nama_jurusan,
                'jumlah_peserta' => $summary['total'],
            ],
            'rekap' => $summary,
        ]);
    }

    /**
     * Daftar tanggal (pertemuan) yang sudah punya catatan absensi,
     * dengan rekap per pertemuan.
     */
    public function sessions(Request $request, int $kelasId)
    {
        $dosenId = $this->authorizeDosen($request);

        $this->getOwnedClass($dosenId, $kelasId);

        $rows = DB::table('absensi')
            ->where('kelas_id', $kelasId)
            ->groupBy('tanggal')
            ->select('tanggal')
            ->addSelect(DB::raw('COUNT(*) as tercatat'))
            ->addSelect(
                DB::raw(
                    "SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                    SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                    SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as alpa"
                )
            )
            ->orderByDesc('tanggal')
            ->get();

        return response()->json($rows);
    }

    /**
     * Simpan absensi manual (harian) untuk satu kelas.
     * Kirim ulang absen mahasiswa mana pun yang berubah; baris
     * yang sudah ada akan diperbarui.
     */
    public function store(Request $request, int $kelasId)
    {
        $dosenId = $this->authorizeDosen($request);

        $validated = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.mahasiswa_id' => ['required', 'integer'],
            'entries.*.status' => [
                'required',
                Rule::in(['hadir', 'izin', 'sakit', 'alpa']),
            ],
            'entries.*.catatan' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $this->getOwnedClass($dosenId, $kelasId);

        $tanggal = $validated['tanggal'];

        $enrolledIds = DB::table('peserta_kelas')
            ->where('kelas_id', $kelasId)
            ->pluck('mahasiswa_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = [];
        foreach ($validated['entries'] as $entry) {
            if (! in_array((int) $entry['mahasiswa_id'], $enrolledIds, true)) {
                return response()->json([
                    'message' => 'Mahasiswa_id '.$entry['mahasiswa_id'].' bukan peserta kelas ini.',
                ], 422);
            }

            $rows[] = [
                'kelas_id' => $kelasId,
                'mahasiswa_id' => (int) $entry['mahasiswa_id'],
                'tanggal' => $tanggal,
                'status' => $entry['status'],
                'catatan' => $entry['catatan'] ?? null,
                'created_at' => now(),
            ];
        }

        DB::table('absensi')->upsert(
            $rows,
            ['kelas_id', 'mahasiswa_id', 'tanggal'],
            ['status', 'catatan']
        );

        return response()->json([
            'message' => 'Absensi berhasil disimpan.',
            'rekap' => $this->attendanceSummary($kelasId, $tanggal),
        ]);
    }

    private function authorizeDosen(Request $request): int
    {
        abort_unless($request->user()?->role === 'dosen', 403, 'Hanya dosen yang dapat mengakses absensi.');

        return (int) DB::table('dosen')
            ->where('user_id', $request->user()->id)
            ->value('id');
    }

    private function getOwnedClass(int $dosenId, int $kelasId)
    {
        $class = DB::table('kelas as k')
            ->join('matkul as m', 'm.id', '=', 'k.matkul_id')
            ->join('jurusan as jur', 'jur.id', '=', 'm.jurusan_id')
            ->where('k.id', $kelasId)
            ->where('k.dosen_id', $dosenId)
            ->select(
                'k.id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'k.dosen_id',
                'm.kode_matkul',
                'm.nama_matkul',
                'm.jurusan_id',
                'jur.kode_jurusan',
                'jur.nama_jurusan'
            )
            ->first();

        abort_if(! $class, 404, 'Kelas tidak ditemukan.');

        return $class;
    }

    private function attendanceSummary(int $kelasId, string $tanggal): array
    {
        $rows = DB::table('absensi')
            ->where('kelas_id', $kelasId)
            ->where('tanggal', $tanggal)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $totalTercatat = (int) $rows->sum();

        $totalPeserta = (int) DB::table('peserta_kelas')
            ->where('kelas_id', $kelasId)
            ->count();

        return [
            'hadir' => (int) ($rows['hadir'] ?? 0),
            'izin' => (int) ($rows['izin'] ?? 0),
            'sakit' => (int) ($rows['sakit'] ?? 0),
            'alpa' => (int) ($rows['alpa'] ?? 0),
            'tercatat' => $totalTercatat,
            'total' => $totalPeserta,
            'belum_dicatat' => max(0, $totalPeserta - $totalTercatat),
        ];
    }

    /**
     * Semester mahasiswa dihitung dari angkatan,
     * tahun ajaran kelas, dan term kelas.
     */
    private function studentSemester($class, int $angkatan): ?int
    {
        $yearStart = (int) (explode('/', trim($class->tahun_ajaran))[0] ?? 0);

        if ($yearStart <= 0) {
            return null;
        }

        $semester = ($yearStart - $angkatan) * 2
            + (($class->term === 'genap') ? 2 : 1);

        return max(1, min(14, $semester));
    }

    private function classSemesterLabel($class): string
    {
        $term = $class->term
            ? ucfirst($class->term)
            : 'Ganjil';

        return $term.' '.$class->tahun_ajaran;
    }
}