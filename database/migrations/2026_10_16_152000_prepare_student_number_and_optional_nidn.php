<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dosen', function (Blueprint $table) {
            $table->string('nidn', 50)->nullable()->change();
        });

        Schema::create('nim_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('next_value')->default(1);
        });

        $nextNimValue = 1;
        foreach (DB::table('mahasiswa')->whereNotNull('nim')->orderBy('id')->cursor() as $student) {
            if (preg_match('/^\d{1,8}$/', $student->nim) === 1) {
                $nextNimValue = max($nextNimValue, (int) $student->nim + 1);
            }
        }

        DB::table('nim_sequences')->insert([
            'id' => 1,
            'next_value' => $nextNimValue,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nim_sequences');

        Schema::table('dosen', function (Blueprint $table) {
            $table->string('nidn', 50)->nullable(false)->change();
        });
    }
};
