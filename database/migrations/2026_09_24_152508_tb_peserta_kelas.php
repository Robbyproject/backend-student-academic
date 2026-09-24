<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_peserta_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kelas_id');
            $table->uuid('mahasiswa_id');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('kelas_id')
                ->references('id')
                ->on('tb_kelas')
                ->onDelete('cascade');
            $table->foreign('mahasiswa_id')
                ->references('id')
                ->on('tb_mahasiswa')
                ->onDelete('cascade');
            $table->unique(
                ['kelas_id', 'mahasiswa_id'],
                'uq_peserta_kelas'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_peserta_kelas');
    }
};