<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peserta_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')
                ->constrained('mahasiswa')
                ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique([
                'kelas_id',
                'mahasiswa_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_kelas');
    }
};