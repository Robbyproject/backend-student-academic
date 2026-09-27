<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AcademicClassController;
use App\Http\Controllers\AcademicCatalogController;
use App\Http\Controllers\ClassParticipantController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubmissionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/tasks', [TaskController::class, 'index']);
Route::get('/schedules', [ScheduleController::class, 'index']);
Route::get('/academic-classes', [AcademicClassController::class, 'index']);
Route::get('/academic-classes/{id}',[AcademicClassController::class, 'show']);
Route::get('/departments', [AcademicCatalogController::class, 'departments']);
Route::get('/lecturers', [AcademicCatalogController::class, 'lecturers']);
Route::get('/courses', [AcademicCatalogController::class, 'courses']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/class-participants', [ClassParticipantController::class, 'index']);
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
});