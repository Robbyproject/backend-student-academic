<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $this->authorizeStudent($user->role);

        $profile = DB::table('mahasiswa')
            ->join('jurusan', 'jurusan.id', '=', 'mahasiswa.jurusan_id')
            ->where('mahasiswa.user_id', $user->id)
            ->select(
                'mahasiswa.id',
                'mahasiswa.nim',
                'mahasiswa.nama',
                'mahasiswa.jurusan_id',
                'jurusan.kode_jurusan',
                'jurusan.nama_jurusan',
                'mahasiswa.angkatan',
                'mahasiswa.no_telepon',
                'mahasiswa.jenis_kelamin'
            )
            ->first();

        $isComplete = $profile !== null
            && $profile->nim !== null
            && $profile->jurusan_id !== null
            && $profile->angkatan !== null
            && $profile->no_telepon !== null
            && $profile->jenis_kelamin !== null;

        return response()->json([
            'profile_status' => $isComplete ? 'complete' : 'incomplete',
            'profile' => $profile,
            'account' => [
                'nama' => $user->nama,
                'email' => $user->email,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $this->authorizeStudent($user->role);

        $validated = $request->validate([
            'user_id' => ['prohibited'],
            'nim' => ['prohibited'],
            'jurusan_id' => ['prohibited'],
            'angkatan' => ['prohibited'],
            'no_telepon' => ['required', 'string', 'max:30'],
            'jenis_kelamin' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
        ]);

        $existingProfile = DB::table('mahasiswa')->where('user_id', $user->id)->first();
        $profileId = DB::transaction(function () use ($validated, $user, $existingProfile) {
            $profileData = [
                ...$validated,
                'nama' => $user->nama ?? $user->email,
            ];

            if ($existingProfile) {
                DB::table('mahasiswa')
                    ->where('id', $existingProfile->id)
                    ->update($profileData);

                return $existingProfile->id;
            }

            abort(409, 'Profil akademik belum dibuat oleh admin.');
        });

        return response()->json([
            'message' => 'Profil mahasiswa berhasil disimpan.',
            'profile_status' => 'complete',
            'data' => [
                'id' => $profileId,
                'nama' => $user->nama,
                'no_telepon' => $validated['no_telepon'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'nim' => $existingProfile->nim,
                'jurusan_id' => $existingProfile->jurusan_id,
                'angkatan' => $existingProfile->angkatan,
            ],
        ]);
    }

    private function authorizeStudent(string $role): void
    {
        abort_unless($role === 'mahasiswa', 403, 'Hanya mahasiswa yang dapat melihat atau mengubah profil ini.');
    }
}
