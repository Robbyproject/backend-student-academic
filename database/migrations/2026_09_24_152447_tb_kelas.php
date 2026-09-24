<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('matkul_id');
            $table->uuid('dosen_id');
            $table->string('nama_kelas', 50);
            $table->string('tahun_ajaran', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('matkul_id')
                ->references('id')
                ->on('tb_matkul')
                ->onDelete('cascade');
            $table->foreign('dosen_id')
                ->references('id')
                ->on('tb_dosen')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_kelas');
    }
};