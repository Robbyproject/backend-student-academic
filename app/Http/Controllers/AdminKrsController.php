<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminKrsController extends Controller
{
    public function periods(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json(
            DB::table('krs_periods')
                ->orderByDesc('academic_year_start')
                ->orderBy('term')
                ->get()
        );
    }

    public function storePeriod(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'academic_year_start' => ['required', 'integer', 'between:2000,2100'],
            'term' => ['required', 'in:ganjil,genap'],
            'is_active' => ['sometimes', 'boolean'],
            'registration_open' => ['sometimes', 'boolean'],
        ]);

        $existing = DB::table('krs_periods')
            ->where('academic_year_start', $validated['academic_year_start'])
            ->where('term', $validated['term'])
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'term' => 'Periode akademik tersebut sudah terdaftar.',
            ]);
        }

        $active = $validated['is_active'] ?? false;
        $open = $validated['registration_open'] ?? false;

        if ($open && ! $active) {
            throw ValidationException::withMessages([
                'registration_open' => 'Periode harus aktif sebelum pengisian KRS dibuka.',
            ]);
        }

        $periodId = DB::transaction(function () use ($validated, $active, $open) {
            if ($active) {
                DB::table('krs_periods')->update([
                    'is_active' => false,
                    'registration_open' => false,
                    'updated_at' => now(),
                ]);
            }

            return DB::table('krs_periods')->insertGetId([
                'academic_year_start' => $validated['academic_year_start'],
                'term' => $validated['term'],
                'is_active' => $active,
                'registration_open' => $open,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Periode KRS berhasil dibuat.',
            'period_id' => $periodId,
        ], 201);
    }

    public function updatePeriod(Request $request, int $periodId)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'registration_open' => ['sometimes', 'boolean'],
        ]);

        $period = DB::table('krs_periods')->where('id', $periodId)->first();
        abort_if(! $period, 404, 'Periode KRS tidak ditemukan.');

        $active = $validated['is_active'] ?? (bool) $period->is_active;
        $open = $active
            ? ($validated['registration_open'] ?? (bool) $period->registration_open)
            : false;

        if ($open && ! $active) {
            throw ValidationException::withMessages([
                'registration_open' => 'Periode harus aktif sebelum pengisian KRS dibuka.',
            ]);
        }

        DB::transaction(function () use ($periodId, $active, $open) {
            if ($active) {
                DB::table('krs_periods')
                    ->where('id', '!=', $periodId)
                    ->update([
                        'is_active' => false,
                        'registration_open' => false,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('krs_periods')->where('id', $periodId)->update([
                'is_active' => $active,
                'registration_open' => $open,
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Periode KRS berhasil diperbarui.']);
    }

    public function packages(Request $request)
    {
        $this->authorizeAdmin($request);

        $packages = DB::table('curriculum_packages as p')
            ->join('jurusan as j', 'j.id', '=', 'p.jurusan_id')
            ->select('p.id', 'p.jurusan_id', 'j.nama_jurusan', 'p.semester', 'p.name')
            ->orderBy('j.nama_jurusan')
            ->orderBy('p.semester')
            ->get()
            ->map(function ($package) {
                $package->courses = DB::table('curriculum_package_courses as pc')
                    ->join('matkul as m', 'm.id', '=', 'pc.matkul_id')
                    ->where('pc.curriculum_package_id', $package->id)
                    ->select('m.id', 'm.kode_matkul', 'm.nama_matkul', 'm.sks')
                    ->orderBy('m.kode_matkul')
                    ->get();

                return $package;
            });

        return response()->json($packages);
    }

    public function storePackage(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $this->validatePackage($request);
        $this->ensureCoursesBelongToDepartment($validated);

        $exists = DB::table('curriculum_packages')
            ->where('jurusan_id', $validated['jurusan_id'])
            ->where('semester', $validated['semester'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'semester' => 'Paket untuk jurusan dan semester tersebut sudah tersedia.',
            ]);
        }

        $packageId = DB::transaction(function () use ($validated) {
            $packageId = DB::table('curriculum_packages')->insertGetId([
                'jurusan_id' => $validated['jurusan_id'],
                'semester' => $validated['semester'],
                'name' => $validated['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->replacePackageCourses($packageId, $validated['matkul_ids']);

            return $packageId;
        });

        return response()->json([
            'message' => 'Paket kurikulum berhasil dibuat.',
            'package_id' => $packageId,
        ], 201);
    }

    public function updatePackage(Request $request, int $packageId)
    {
        $this->authorizeAdmin($request);

        $validated = $this->validatePackage($request);
        $this->ensureCoursesBelongToDepartment($validated);

        $package = DB::table('curriculum_packages')->where('id', $packageId)->first();
        abort_if(! $package, 404, 'Paket kurikulum tidak ditemukan.');

        $duplicate = DB::table('curriculum_packages')
            ->where('jurusan_id', $validated['jurusan_id'])
            ->where('semester', $validated['semester'])
            ->where('id', '!=', $packageId)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'semester' => 'Paket untuk jurusan dan semester tersebut sudah tersedia.',
            ]);
        }

        DB::transaction(function () use ($packageId, $validated) {
            DB::table('curriculum_packages')->where('id', $packageId)->update([
                'jurusan_id' => $validated['jurusan_id'],
                'semester' => $validated['semester'],
                'name' => $validated['name'],
                'updated_at' => now(),
            ]);

            $this->replacePackageCourses($packageId, $validated['matkul_ids']);
        });

        return response()->json(['message' => 'Paket kurikulum berhasil diperbarui.']);
    }

    private function validatePackage(Request $request): array
    {
        return $request->validate([
            'jurusan_id' => ['required', 'integer', 'exists:jurusan,id'],
            'semester' => ['required', 'integer', 'between:1,14'],
            'name' => ['required', 'string', 'max:100'],
            'matkul_ids' => ['required', 'array', 'min:1'],
            'matkul_ids.*' => ['required', 'integer', 'distinct', 'exists:matkul,id'],
        ]);
    }

    private function ensureCoursesBelongToDepartment(array $validated): void
    {
        $courseCount = DB::table('matkul')
            ->where('jurusan_id', $validated['jurusan_id'])
            ->whereIn('id', $validated['matkul_ids'])
            ->count();

        if ($courseCount !== count($validated['matkul_ids'])) {
            throw ValidationException::withMessages([
                'matkul_ids' => 'Semua mata kuliah dalam paket harus berasal dari jurusan yang dipilih.',
            ]);
        }
    }

    private function replacePackageCourses(int $packageId, array $courseIds): void
    {
        DB::table('curriculum_package_courses')
            ->where('curriculum_package_id', $packageId)
            ->delete();

        $now = now();
        DB::table('curriculum_package_courses')->insert(
            collect($courseIds)->map(fn ($courseId) => [
                'curriculum_package_id' => $packageId,
                'matkul_id' => $courseId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya admin yang dapat mengatur KRS.');
    }
}
