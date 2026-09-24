<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_matkul', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_matkul', 50)->unique();
            $table->string('nama_matkul', 255);
            $table->integer('sks');
            $table->uuid('jurusan_id');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('jurusan_id')
                ->references('id')
                ->on('tb_jurusan')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_matkul');
    }
};