<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResidentController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\VitalSignController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    // Auth routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware('auth:sanctum');

    // Residents
    Route::apiResource('residents', ResidentController::class)->middleware('auth:sanctum');
    Route::get('/residents/{id}/appointments', [ResidentController::class, 'appointments'])->middleware('auth:sanctum');
    Route::get('/residents/{id}/vitals', [ResidentController::class, 'vitals'])->middleware('auth:sanctum');

    // Appointments
    Route::apiResource('appointments', AppointmentController::class)->middleware('auth:sanctum');
    Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])->middleware('auth:sanctum');

    // Vital Signs
    Route::apiResource('vitals', VitalSignController::class)->middleware('auth:sanctum');
});

