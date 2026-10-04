<?php

use App\Http\Controllers\AcademicClassController;
use App\Http\Controllers\AdminAcademicController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MatkulController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/schedules', [ScheduleController::class, 'index']);
Route::get('/academic-classes', [AcademicClassController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::get('/tasks/{mahasiswaId}', [TaskController::class, 'index']);
Route::get('/academic-classes/{id}', [AcademicClassController::class, 'show']);
Route::get('/matkul', [MatkulController::class, 'index']);
Route::get('/academic-classes/lecturers/{matkulId}', [AcademicClassController::class, 'lecturersByCourse']);
Route::get('/admin/academic-data', [AdminAcademicController::class, 'data']);
Route::post('/admin/academic-classes', [AdminAcademicController::class, 'store']);
