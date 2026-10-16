<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class StudentNumberGenerator
{
    public function next(): string
    {
        $sequence = DB::table('nim_sequences')->where('id', 1)->lockForUpdate()->first();

        if (! $sequence) {
            throw new RuntimeException('Urutan NIM belum diinisialisasi. Jalankan migrasi database.');
        }

        $nextValue = (int) $sequence->next_value;

        while ($nextValue <= 99_999_999) {
            $nim = str_pad((string) $nextValue, 8, '0', STR_PAD_LEFT);

            if (! DB::table('mahasiswa')->where('nim', $nim)->exists()) {
                DB::table('nim_sequences')->where('id', 1)->update([
                    'next_value' => $nextValue + 1,
                ]);

                return $nim;
            }

            $nextValue++;
        }

        throw new RuntimeException('Urutan NIM 8 digit sudah habis.');
    }
}
