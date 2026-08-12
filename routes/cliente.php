<?php

use App\Http\Controllers\Cliente\DashboardController;
use App\Http\Controllers\Cliente\ProgressController;
use App\Http\Controllers\Cliente\SavedMealController;
use App\Http\Controllers\Cliente\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel del cliente
|--------------------------------------------------------------------------
| De sólo lectura casi entero a propósito: un cliente ve su propio progreso,
| rutina, pagos y asistencia, pero no edita nada aquí. Editar es cosa de
| recepción o del entrenador.
*/

Route::prefix('cliente')->name('cliente.')->middleware('rol:cliente')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('progreso', ProgressController::class)->name('progreso');
    Route::post('progreso', [ProgressController::class, 'guardar'])->name('progreso.guardar');
    Route::post('progreso/comidas', [ProgressController::class, 'guardarComida'])->name('progreso.comidas.guardar');
    // Fase 4 del plan de nutrición: "Mis platos habituales".
    Route::post('platos', [SavedMealController::class, 'store'])->name('platos.guardar');
    Route::post('platos/{plato}/usar', [SavedMealController::class, 'usar'])->name('platos.usar');
    Route::delete('platos/{plato}', [SavedMealController::class, 'destroy'])->name('platos.destroy');
    Route::post('resena', [TestimonialController::class, 'store'])->name('resena.store');
});
