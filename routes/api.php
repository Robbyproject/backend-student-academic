<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Route 
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes 
Route::middleware('auth:sanctum')->group(function () {
    
    // Route Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Route untuk mengambil data user yang sedang login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

});