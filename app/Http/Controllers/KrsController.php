<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KrsController extends Controller
{
    public function options(Request $request)
    {
        $student = $this->studentFor($request);
        $period = $this->activePeriod();
        $semester = $this->semesterFor($student, $period);

        $package = DB::table('curriculum_packages')
            ->where('jurusan_id', $student->jurusan_id)
            ->where('semester', $semester)
            ->first();

        if (! $package) {
            return response()->json([
                'message' => 'Paket mata kuliah untuk semester mahasiswa belum tersedia.',
                'semester' => $semester,
            ], 404);
        }

        $courses = DB::table('curriculum_package_courses as pc')
            ->join('matkul as m', 'm.id', '=', 'pc.matkul_id')
            ->where('pc.curriculum_package_id', $package->id)
            ->select('m.id', 'm.kode_matkul', 'm.nama_matkul', 'm.sks')
            ->orderBy('m.kode_matkul')
            ->get();

        $classes = DB::table('kelas as k')
            ->join('dosen as d', 'd.id', '=', 'k.dosen_id')
            ->leftJoin('jadwal as j', 'j.kelas_id', '=', 'k.id')
            ->where('k.tahun_ajaran', $this->academicYear($period))
            ->where('k.term', $period->term)
            ->whereIn('k.matkul_id', $courses->pluck('id'))
            ->select(
                'k.id',
                'k.matkul_id',
                'k.nama_kelas',
                'k.tahun_ajaran',
                'k.term',
                'd.nama as nama_dosen',
                'j.hari',
                'j.jam_mulai',
                'j.jam_selesai',
                'j.ruangan'
            )
            ->orderBy('k.nama_kelas')
            ->orderBy('j.hari')
            ->orderBy('j.jam_mulai')
            ->get()
            ->groupBy('id')
            ->map(function ($rows) {
                $class = $rows->first();

                return [
                    'id' => $class->id,
                    'matkul_id' => $class->matkul_id,
                    'nama_kelas' => $class->nama_kelas,
                    'nama_dosen' => $class->nama_dosen,
                    'schedules' => $rows
                        ->filter(fn ($row) => $row->hari !== null)
                        ->map(fn ($row) => [
                            'hari' => $row->hari,
                            'jam_mulai' => $row->jam_mulai,
                            'jam_selesai' => $row->jam_selesai,
                            'ruangan' => $row->ruangan,
                        ])
                        ->values(),
                ];
            })
            ->groupBy('matkul_id');

        return response()->json([
            'period' => [
                'id' => $period->id,
                'academic_year' => $this->academicYear($period),
                'term' => $period->term,
            ],
            'semester' => $semester,
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'courses' => $courses->map(fn ($course) => [
                    'id' => $course->id,
                    'kode_matkul' => $course->kode_matkul,
                    'nama_matkul' => $course->nama_matkul,
                    'sks' => $course->sks,
                    'classes' => $classes->get($course->id, collect())->values(),
                ])->values(),
            ],
        ]);
    }

    public function show(Request $request)
    {
        $student = $this->studentFor($request);
        $period = $this->activePeriod();

        $krs = DB::table('krs')
            ->where('mahasiswa_id', $student->id)
            ->where('krs_period_id', $period->id)
            ->first();

        if (! $krs) {
            return response()->json([
                'period' => [
                    'id' => $period->id,
                    'academic_year' => $this->academicYear($period),
                    'term' => $period->term,
                ],
                'krs' => null,
            ]);
        }

        $details = DB::table('krs_details as kd')
            ->join('matkul as m', 'm.id', '=', 'kd.matkul_id')
            ->join('kelas as k', 'k.id', '=', 'kd.kelas_id')
            ->join('dosen as d', 'd.id', '=', 'k.dosen_id')
            ->where('kd.krs_id', $krs->id)
            ->select(
                'm.id as matkul_id',
                'm.kode_matkul',
                'm.nama_matkul',
                'm.sks',
                'k.id as kelas_id',
                'k.nama_kelas',
                'd.nama as nama_dosen'
            )
            ->orderBy('m.kode_matkul')
            ->get();

        return response()->json([
            'period' => [
                'id' => $period->id,
                'academic_year' => $this->academicYear($period),
                'term' => $period->term,
            ],
            'krs' => [
                'id' => $krs->id,
                'semester' => $this->semesterFor($student, $period),
                'status' => $krs->status,
                'total_sks' => $krs->total_sks,
                'courses' => $details,
            ],
        ]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'kelas_ids' => ['required', 'array', 'min:1'],
            'kelas_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);

        $student = $this->studentFor($request);

        return DB::transaction(function () use ($student, $validated) {
            $period = DB::table('krs_periods')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $period || ! $period->registration_open) {
                return response()->json([
                    'message' => 'Periode pengisian KRS sedang tidak dibuka.',
                ], 409);
            }

            $semester = $this->semesterFor($student, $period);
            $package = DB::table('curriculum_packages')
                ->where('jurusan_id', $student->jurusan_id)
                ->where('semester', $semester)
                ->first();

            if (! $package) {
                return response()->json([
                    'message' => 'Paket mata kuliah untuk semester mahasiswa belum tersedia.',
                ], 409);
            }

            $courseIds = DB::table('curriculum_package_courses')
                ->where('curriculum_package_id', $package->id)
                ->pluck('matkul_id')
                ->map(fn ($id) => (int) $id);

            $classIds = collect($validated['kelas_ids'])->map(fn ($id) => (int) $id);

            if ($classIds->count() !== $courseIds->count()) {
                throw ValidationException::withMessages([
                    'kelas_ids' => 'Pilih tepat satu kelas untuk setiap mata kuliah paket.',
                ]);
            }

            $classes = DB::table('kelas as k')
                ->join('matkul as m', 'm.id', '=', 'k.matkul_id')
                ->whereIn('k.id', $classIds)
                ->where('m.jurusan_id', $student->jurusan_id)
                ->where('k.tahun_ajaran', $this->academicYear($period))
                ->where('k.term', $period->term)
                ->select('k.id', 'k.matkul_id', 'm.sks')
                ->get();

            if ($classes->count() !== $classIds->count()
                || $classes->pluck('matkul_id')->map(fn ($id) => (int) $id)->sort()->values()->all()
                    !== $courseIds->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'kelas_ids' => 'Pilihan harus berisi satu kelas yang tersedia untuk setiap mata kuliah paket.',
                ]);
            }

            $this->ensureNoScheduleConflicts($classes);

            $now = now();
            $krs = DB::table('krs')
                ->where('mahasiswa_id', $student->id)
                ->where('krs_period_id', $period->id)
                ->first();

            $values = [
                'mahasiswa_id' => $student->id,
                'krs_period_id' => $period->id,
                'curriculum_package_id' => $package->id,
                'total_sks' => $classes->sum('sks'),
                'status' => 'approved',
                'updated_at' => $now,
            ];

            if ($krs) {
                $oldClassIds = DB::table('krs_details')
                    ->where('krs_id', $krs->id)
                    ->pluck('kelas_id');

                DB::table('peserta_kelas')
                    ->where('mahasiswa_id', $student->id)
                    ->whereIn('kelas_id', $oldClassIds)
                    ->delete();

                DB::table('krs')->where('id', $krs->id)->update($values);
                $krsId = $krs->id;

                DB::table('krs_details')->where('krs_id', $krsId)->delete();
            } else {
                $values['created_at'] = $now;
                $krsId = DB::table('krs')->insertGetId($values);
            }

            foreach ($classes as $class) {
                DB::table('krs_details')->insert([
                    'krs_id' => $krsId,
                    'matkul_id' => $class->matkul_id,
                    'kelas_id' => $class->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('peserta_kelas')->insertOrIgnore([
                    'kelas_id' => $class->id,
                    'mahasiswa_id' => $student->id,
                    'created_at' => $now,
                ]);
            }

            return response()->json([
                'message' => 'KRS berhasil disimpan dan disetujui.',
                'krs_id' => $krsId,
                'status' => 'approved',
                'total_sks' => $values['total_sks'],
            ]);
        });
    }

    private function studentFor(Request $request): object
    {
        abort_unless($request->user()?->role === 'mahasiswa', 403, 'Hanya mahasiswa yang dapat mengakses KRS.');

        $student = DB::table('mahasiswa')
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if(! $student, 404, 'Data mahasiswa tidak ditemukan.');

        return $student;
    }

    private function activePeriod(): object
    {
        $period = DB::table('krs_periods')
            ->where('is_active', true)
            ->first();

        abort_if(! $period, 409, 'Periode akademik aktif belum diatur.');

        return $period;
    }

    private function semesterFor(object $student, object $period): int
    {
        $termNumber = $period->term === 'ganjil' ? 1 : 2;
        $semester = (($period->academic_year_start - $student->angkatan) * 2) + $termNumber;

        abort_if($semester < 1 || $semester > 14, 409, 'Semester mahasiswa tidak valid untuk periode akademik aktif.');

        return $semester;
    }

    private function academicYear(object $period): string
    {
        return $period->academic_year_start.'/'.($period->academic_year_start + 1);
    }

    private function ensureNoScheduleConflicts($classes): void
    {
        $schedules = DB::table('jadwal')
            ->whereIn('kelas_id', $classes->pluck('id'))
            ->get(['kelas_id', 'hari', 'jam_mulai', 'jam_selesai']);

        foreach ($schedules as $index => $schedule) {
            foreach ($schedules->slice($index + 1) as $other) {
                if ($schedule->kelas_id !== $other->kelas_id
                    && $schedule->hari === $other->hari
                    && $schedule->jam_mulai < $other->jam_selesai
                    && $other->jam_mulai < $schedule->jam_selesai) {
                    throw ValidationException::withMessages([
                        'kelas_ids' => 'Jadwal kelas yang dipilih saling bertabrakan.',
                    ]);
                }
            }
        }
    }
}
