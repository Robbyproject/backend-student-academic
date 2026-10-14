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

Route::get('/tasks/{mahasiswaId}', [TaskController::class, 'index']);
Route::get('/academic-classes/{id}', [AcademicClassController::class, 'show']);
Route::get('/matkul', [MatkulController::class, 'index']);
Route::get('/academic-classes/lecturers/{matkulId}', [AcademicClassController::class, 'lecturersByCourse']);
