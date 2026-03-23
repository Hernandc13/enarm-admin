<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\SimulatorController;
use App\Http\Controllers\Api\SimulatorAttemptController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\SimulatorHistoryController;
use App\Http\Controllers\Api\AttemptReportController;

// Login APP-only
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Validación de acceso por email (para Moodle users) + token Sanctum
Route::post('/auth/check-access', [AuthController::class, 'checkAccess'])->middleware('throttle:30,1');

// Recuperar contraseña
Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:10,1');

// Registro de usuario app
Route::post('/auth/register', [RegisterController::class, 'register'])
    ->middleware('throttle:10,1');

// Endpoints protegidos por token Sanctum
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/simuladores', [SimulatorController::class, 'index']);
    Route::get('/simuladores/{simulator}', [SimulatorController::class, 'show']);

    Route::post('/simuladores/{simulator}/iniciar', [SimulatorAttemptController::class, 'start']);
    Route::get('/intentos/{attempt}/preguntas', [SimulatorAttemptController::class, 'questions']);

    Route::post('/intentos/{attempt}/responder', [SimulatorAttemptController::class, 'answer']);
    Route::post('/intentos/{attempt}/respuestas', [SimulatorAttemptController::class, 'answer']);

    Route::post('/intentos/{attempt}/abandonar', [SimulatorAttemptController::class, 'abandon']);
    Route::post('/intentos/{attempt}/finalizar', [SimulatorAttemptController::class, 'finish']);

    Route::get('/tablero', [DashboardController::class, 'me']);
    Route::get('/ranking', [RankingController::class, 'index']);
    Route::get('/simuladores/{simulator}/historial', [SimulatorHistoryController::class, 'show']);
    Route::get('/intentos/{attempt}/reporte', [AttemptReportController::class, 'show']);
});