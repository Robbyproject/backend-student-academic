<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('kelas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('matkul_id')
            ->constrained('matkul')
            ->cascadeOnDelete();
        $table->foreignId('dosen_id')
            ->constrained('dosen')
            ->restrictOnDelete();
        $table->string('nama_kelas', 50);
        $table->string('tahun_ajaran', 20);
        $table->timestamp('created_at')->useCurrent();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};