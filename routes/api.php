<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AcademicClassController;
use App\Http\Controllers\MatkulController;
use App\Http\Controllers\AdminAcademicController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/tasks', [TaskController::class, 'index']);
Route::get('/schedules', [ScheduleController::class, 'index']);
Route::get('/academic-classes', [AcademicClassController::class, 'index']);
Route::get('/academic-classes/{id}', [AcademicClassController::class, 'show']);
Route::get('/departments', [AcademicCatalogController::class, 'departments']);
Route::get('/lecturers', [AcademicCatalogController::class, 'lecturers']);
Route::get('/courses', [AcademicCatalogController::class, 'courses']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/class-participants', [ClassParticipantController::class, 'index']);
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
});

Route::get('/academic-classes/{id}', [AcademicClassController::class, 'show']);
Route::get('/matkul', [MatkulController::class, 'index']);

Route::get(
    '/academic-classes/lecturers/{matkulId}',
    [AcademicClassController::class, 'lecturersByCourse']
);


Route::get(
    '/admin/academic-data',
    [AdminAcademicController::class, 'data']
);
Route::post(
    '/admin/academic-classes',
    [AdminAcademicController::class, 'store']
);

Route::get('/tasks/{mahasiswaId}', [TaskController::class, 'index']);
