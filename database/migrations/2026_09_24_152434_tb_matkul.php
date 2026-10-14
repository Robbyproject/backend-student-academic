<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('matkul', function (Blueprint $table) {
        $table->id();
        $table->string('kode_matkul', 50)->unique();
        $table->string('nama_matkul', 255);
        $table->integer('sks');
        $table->foreignId('jurusan_id')
            ->constrained('jurusan')
            ->restrictOnDelete();
        $table->timestamp('created_at')->useCurrent();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('matkul');
    }
};