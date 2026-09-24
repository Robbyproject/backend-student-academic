<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('nim', 50)->unique();
            $table->string('nama', 255);
            $table->uuid('jurusan_id');
            $table->integer('angkatan');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')
                ->references('id')
                ->on('tb_users')
                ->onDelete('cascade');
            $table->foreign('jurusan_id')
                ->references('id')
                ->on('tb_jurusan')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_mahasiswa');
    }
};