<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_tugas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kelas_id');
            $table->string('judul', 255);
            $table->text('deskripsi')->nullable();
            $table->text('file_attachment_path')->nullable();
            $table->timestamp('deadline');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('kelas_id')
                ->references('id')
                ->on('tb_kelas')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_tugas');
    }
};
