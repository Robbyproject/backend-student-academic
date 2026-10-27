<?php

use App\Http\Controllers\AcademicClassController;
use App\Http\Controllers\AdminAcademicController;
use App\Http\Controllers\AdminKrsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KrsController;
use App\Http\Controllers\MatkulController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\ClassParticipantController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/schedules', [ScheduleController::class, 'index']);
Route::post('/schedules', [ScheduleController::class, 'store']);
Route::put('/schedules/{id}', [ScheduleController::class, 'update']);
Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Pindahkan rute ini ke dalam Sanctum agar Admin bisa mengaksesnya dengan token
    Route::get('/academic-classes', [AcademicClassController::class, 'index']);
    Route::get('/dosen/departments', [TaskController::class, 'getDepartmentsByLecturer']);
    Route::get('/dosen/classes-by-jurusan/{jurusanId}', [TaskController::class, 'getClassesByJurusanAndLecturer']);

    Route::post('/submissions', [SubmissionController::class, 'store']);
    Route::get('/submissions', [App\Http\Controllers\SubmissionController::class, 'index']);

    // Rute untuk Peserta Kelas & Mahasiswa
    Route::get('/classes/participants', [ClassParticipantController::class, 'index']);
    Route::post('/classes/participants', [ClassParticipantController::class, 'store']);
    Route::delete('/classes/participants/{id}', [ClassParticipantController::class, 'destroy']);
    
    Route::get('/tasks', [TaskController::class, 'index']);

    Route::get('/departments', function () {
    $jurusan = \Illuminate\Support\Facades\DB::table('jurusan')->get();
    return response()->json($jurusan);
    });
    
    // Endpoint untuk mengambil daftar mahasiswa
    Route::get('/students', [App\Http\Controllers\StudentController::class, 'index']);

    Route::get('/academic-classes/by-jurusan/{jurusanId}', [AcademicClassController::class, 'getClassesByJurusan']);

    Route::get('/dosen/tasks', [TaskController::class, 'index']); 
    Route::get('/dosen/tasks/{taskId}/submissions', [SubmissionController::class, 'getByTask']); 

    Route::get('/tasks', [TaskController::class, 'index']); 
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

    Route::get('/tasks/{mahasiswaId}', [TaskController::class, 'index']);

    Route::get('/krs/options', [KrsController::class, 'options']);
    Route::get('/krs', [KrsController::class, 'show']);
    Route::put('/krs', [KrsController::class, 'save']);

    Route::prefix('admin')->group(function () {
        Route::get('/academic-data', [AdminAcademicController::class, 'data']);
        Route::post('/matkul', [AdminAcademicController::class, 'storeCourse']);
        Route::post('/academic-classes', [AdminAcademicController::class, 'store']);
        Route::patch('/academic-classes/{classId}/offering', [AdminAcademicController::class, 'updateClassOffering']);
        Route::get('/krs/periods', [AdminKrsController::class, 'periods']);
        Route::post('/krs/periods', [AdminKrsController::class, 'storePeriod']);
        Route::patch('/krs/periods/{periodId}', [AdminKrsController::class, 'updatePeriod']);
        Route::get('/krs/packages', [AdminKrsController::class, 'packages']);
        Route::post('/krs/packages', [AdminKrsController::class, 'storePackage']);
        Route::put('/krs/packages/{packageId}', [AdminKrsController::class, 'updatePackage']);
    });
});

Route::get('/academic-classes/{id}', [AcademicClassController::class, 'show']);
Route::get('/matkul', [MatkulController::class, 'index']);
Route::get('/academic-classes/lecturers/{matkulId}', [AcademicClassController::class, 'lecturersByCourse']);