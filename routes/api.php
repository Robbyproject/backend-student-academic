<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AcademicClassController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/tasks', [TaskController::class, 'index']);
Route::get('/schedules', [ScheduleController::class, 'index']);
Route::get('/academic-classes', [AcademicClassController::class, 'index']);
Route::get('/academic-classes/{id}',[AcademicClassController::class, 'show']);