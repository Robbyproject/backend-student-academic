<?php

use App\Http\Controllers\AcademicClassController;
use App\Http\Controllers\AdminAcademicController;
use App\Http\Controllers\AdminDirectoryController;
use App\Http\Controllers\AdminKrsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KrsController;
use App\Http\Controllers\MatkulController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/schedules', [ScheduleController::class, 'index']);
Route::post('/schedules', [ScheduleController::class, 'store']);
Route::put('/schedules/{id}', [ScheduleController::class, 'update']);
Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);


Route::get('/academic-classes', [AcademicClassController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/mahasiswa/profile', [StudentProfileController::class, 'show']);
    Route::put('/mahasiswa/profile', [StudentProfileController::class, 'update']);

    Route::get('/krs/options', [KrsController::class, 'options']);
    Route::get('/krs', [KrsController::class, 'show']);
    Route::put('/krs', [KrsController::class, 'save']);

    Route::prefix('admin')->group(function () {
        Route::get('/academic-data', [AdminAcademicController::class, 'data']);
        Route::get('/jurusan', [AdminDirectoryController::class, 'departments']);
        Route::post('/jurusan', [AdminDirectoryController::class, 'storeDepartment']);
        Route::get('/jurusan/{jurusanId}', [AdminDirectoryController::class, 'department']);
        Route::put('/jurusan/{jurusanId}', [AdminDirectoryController::class, 'updateDepartment']);
        Route::patch('/jurusan/{jurusanId}', [AdminDirectoryController::class, 'updateDepartment']);
        Route::delete('/jurusan/{jurusanId}', [AdminDirectoryController::class, 'destroyDepartment']);
        Route::get('/dosen', [AdminDirectoryController::class, 'lecturers']);
        Route::post('/dosen', [AdminDirectoryController::class, 'storeLecturer']);
        Route::get('/dosen/{dosenId}', [AdminDirectoryController::class, 'lecturer']);
        Route::put('/dosen/{dosenId}', [AdminDirectoryController::class, 'updateLecturer']);
        Route::patch('/dosen/{dosenId}', [AdminDirectoryController::class, 'updateLecturer']);
        Route::delete('/dosen/{dosenId}', [AdminDirectoryController::class, 'destroyLecturer']);
        Route::post('/users', [AdminDirectoryController::class, 'storeStudentUser']);
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

Route::get('/tasks/{mahasiswaId}', [TaskController::class, 'index']);
Route::get('/academic-classes/{id}', [AcademicClassController::class, 'show']);
Route::get('/matkul', [MatkulController::class, 'index']);
Route::get('/academic-classes/lecturers/{matkulId}', [AcademicClassController::class, 'lecturersByCourse']);
