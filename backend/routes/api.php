<?php

use App\Http\Controllers\Api\AttendanceEventController;
use App\Http\Controllers\Api\AttendanceSessionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentFacePhotoController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

// Contrato completo en docs/02-diseno.md §5.

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'user-principal'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/me/attendance', [AttendanceSessionController::class, 'myAttendance']);
    Route::get('/me/summary', [AttendanceSessionController::class, 'mySummary']);

    Route::apiResource('students', StudentController::class)->except(['destroy']);
    Route::get('/students/{student}/face-profile', [StudentFacePhotoController::class, 'show']);
    Route::post('/students/{student}/face-photo', [StudentFacePhotoController::class, 'store']);
    Route::put('/students/{student}/face-photo', [StudentFacePhotoController::class, 'update']);

    Route::get('/attendance/sessions', [AttendanceSessionController::class, 'index']);
    Route::patch('/attendance/sessions/{attendanceSession}', [AttendanceSessionController::class, 'update']);
    Route::get('/lab/status', [AttendanceSessionController::class, 'labStatus']);

    Route::get('/incidents', [IncidentController::class, 'index']);
    Route::patch('/incidents/{incident}', [IncidentController::class, 'update']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::patch('/settings', [SettingController::class, 'update']);

    Route::get('/devices', [DeviceController::class, 'index']);
    Route::post('/devices', [DeviceController::class, 'store']);
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);
});

// Rutas exclusivas del device del laboratorio (token con abilities acotadas, §7).
// 'device-principal' es imprescindible además de 'abilities:*': una sesión SPA
// autenticada (User) recibe un TransientToken de Sanctum que aprueba
// cualquier ability, así que 'abilities:*' solo no basta para bloquearla.
Route::middleware(['auth:sanctum', 'device-principal', 'abilities:sync'])
    ->get('/sync/face-catalog', [SyncController::class, 'faceCatalog']);

Route::middleware(['auth:sanctum', 'device-principal', 'abilities:attendance:write'])
    ->post('/attendance/events', [AttendanceEventController::class, 'store']);
