<?php

use App\Http\Controllers\Cliente\DashboardController;
use App\Http\Controllers\Cliente\ProgramController;
use App\Http\Controllers\Cliente\ProgressController;
use App\Http\Controllers\Cliente\RoutineController;
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
    // Módulo canónico de la rutina (ver PROMPT-EJECUCION-MI-RUTINA.md): antes
    // vivía duplicada en el dashboard y en progreso — ahora ambos enlazan
    // acá en vez de repetir el detalle completo.
    Route::get('rutina', RoutineController::class)->name('rutina');
    Route::get('progreso', ProgressController::class)->name('progreso');
    Route::post('progreso', [ProgressController::class, 'guardar'])->name('progreso.guardar');
    Route::post('resena', [TestimonialController::class, 'store'])->name('resena.store');

    // Asignación automática al elegir un programa desde la landing (Parte 3
    // de PLAN-PROGRAMAS.md). El socio autenticado sale del request->user(),
    // nunca de un member_id enviado por el cliente.
    Route::post('programa/asignar', [ProgramController::class, 'asignar'])->name('programa.asignar');

    // Diario de comidas por porciones y "Mis platos habituales" — retirados
    // de la vista de progreso (Parte 4 de PLAN-PROGRAMAS.md, el usuario pidió
    // quitarlos). Las rutas se dan de baja porque solo las usaba
    // progreso.blade.php (verificado, ver PROMPT-EJECUCION-PROGRAMAS.md
    // regla 11); SavedMealController/MealLog y sus tablas se conservan.
});
