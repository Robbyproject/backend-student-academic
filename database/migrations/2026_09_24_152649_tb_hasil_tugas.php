<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_hasil_tugas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tugas_id');
            $table->uuid('mahasiswa_id');
            $table->text('file_submission_path');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan_dosen')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->foreign('tugas_id')
                ->references('id')
                ->on('tb_tugas')
                ->onDelete('cascade');

            $table->foreign('mahasiswa_id')
                ->references('id')
                ->on('tb_mahasiswa')
                ->onDelete('cascade');

            $table->unique(
                ['tugas_id', 'mahasiswa_id'],
                'uq_submission'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_hasil_tugas');
    }
};