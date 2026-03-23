<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ResetPasswordController;

Route::get('/admin/formatos/formato_importacion_usuarios.xlsx', function () {
    $fullPath = storage_path('app/templates/formato_importacion_usuarios.xlsx');

    abort_unless(file_exists($fullPath), 404);

    return response()->download(
        $fullPath,
        'formato_importacion_usuarios.xlsx',
        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
    );
})->middleware(['web', 'auth'])->name('users.template.download');

// ✅ Descargar formato GIFT (banco de preguntas)
Route::get('/admin/formatos/formato_banco.gift', function () {
    $fullPath = storage_path('app/templates/formato_banco.gift');

    abort_unless(file_exists($fullPath), 404);

    return response()->download(
        $fullPath,
        'formato_banco.gift',
        ['Content-Type' => 'text/plain; charset=utf-8']
    );
})->middleware(['web', 'auth'])->name('questions.gift.template.download');

// =====================================================
// RECUPERACIÓN DE CONTRASEÑA
// =====================================================

// Muestra el formulario para capturar nueva contraseña
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
    ->name('password.reset');

// Procesa el cambio de contraseña
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
    ->name('password.update');

// Pantalla final de éxito
Route::get('/reset-password-success', [ResetPasswordController::class, 'success'])
    ->name('password.reset.success');