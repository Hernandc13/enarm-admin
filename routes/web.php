<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin/formatos/formato_importacion_usuarios.xlsx', function () {
    $fullPath = storage_path('app/templates/formato_importacion_usuarios.xlsx');

    abort_unless(file_exists($fullPath), 404);

    return response()->download(
        $fullPath,
        'formato_importacion_usuarios.xlsx',
        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
    );
})->middleware(['web', 'auth'])->name('users.template.download');


// ✅ NUEVO: Descargar formato GIFT (banco de preguntas)
Route::get('/admin/formatos/formato_banco.gift', function () {
    $fullPath = storage_path('app/templates/formato_banco.gift');

    abort_unless(file_exists($fullPath), 404);

    return response()->download(
        $fullPath,
        'formato_banco.gift',
        ['Content-Type' => 'text/plain; charset=utf-8']
    );
})->middleware(['web', 'auth'])->name('questions.gift.template.download');
