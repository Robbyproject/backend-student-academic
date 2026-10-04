<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->string('term', 10)->nullable()->after('tahun_ajaran');
        });

        Schema::create('krs_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('academic_year_start');
            $table->string('term', 10);
            $table->boolean('is_active')->default(false);
            $table->boolean('registration_open')->default(false);
            $table->timestamps();
            $table->unique(['academic_year_start', 'term']);
        });

        Schema::create('curriculum_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurusan_id')
                ->constrained('jurusan')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['jurusan_id', 'semester']);
        });

        Schema::create('curriculum_package_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_package_id')
                ->constrained('curriculum_packages')
                ->cascadeOnDelete();
            $table->foreignId('matkul_id')
                ->constrained('matkul')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['curriculum_package_id', 'matkul_id']);
        });

        Schema::create('krs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')
                ->constrained('mahasiswa')
                ->cascadeOnDelete();
            $table->foreignId('krs_period_id')
                ->constrained('krs_periods')
                ->restrictOnDelete();
            $table->foreignId('curriculum_package_id')
                ->constrained('curriculum_packages')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('total_sks');
            $table->string('status', 20)->default('approved');
            $table->timestamps();
            $table->unique(['mahasiswa_id', 'krs_period_id']);
        });

        Schema::create('krs_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_id')
                ->constrained('krs')
                ->cascadeOnDelete();
            $table->foreignId('matkul_id')
                ->constrained('matkul')
                ->restrictOnDelete();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->restrictOnDelete();
            $table->timestamps();
            $table->unique(['krs_id', 'matkul_id']);
            $table->unique(['krs_id', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_details');
        Schema::dropIfExists('krs');
        Schema::dropIfExists('curriculum_package_courses');
        Schema::dropIfExists('curriculum_packages');
        Schema::dropIfExists('krs_periods');

        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('term');
        });
    }
};
