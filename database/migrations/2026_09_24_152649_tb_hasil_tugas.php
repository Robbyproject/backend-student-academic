<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_tugas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tugas_id')
                ->constrained('tugas')
                ->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')
                ->constrained('mahasiswa')
                ->cascadeOnDelete();
            $table->text('file_submission_path');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan_dosen')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->unique([
                'tugas_id',
                'mahasiswa_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_tugas');
    }
};