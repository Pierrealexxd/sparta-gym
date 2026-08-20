<?php

use App\Http\Controllers\Entrenador\AttendanceController;
use App\Http\Controllers\Entrenador\InscripcionController;
use App\Http\Controllers\Entrenador\MemberController;
use App\Http\Controllers\Entrenador\RoutineController;
use App\Http\Controllers\Entrenador\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel del entrenador
|--------------------------------------------------------------------------
| Todo aquí se acota, dentro de cada controlador, a los clientes que el
| entrenador autenticado tiene realmente asignados: ver
| Trainer::activeMembers() y las comprobaciones en cada método.
*/

Route::prefix('entrenador')->name('entrenador.')->middleware('rol:entrenador')->group(function () {

    // El "Resumen" con KPIs generales se dio de baja (13-08-2026): los
    // indicadores se movieron a vivir dentro de cada módulo que los usa
    // (Rutinas/Inscripciones, Ventas, Asistencia) en vez de una pantalla
    // aparte. La ruta/nombre 'entrenador.dashboard' se conserva como
    // redirect — es la que usa config('sparta.inicio_por_rol') al loguearse.
    Route::get('/', fn () => redirect()->route('entrenador.inscripciones.index'))->name('dashboard');

    Route::get('clientes/{member}', [MemberController::class, 'show'])->name('clientes.show');
    Route::post('clientes/{member}/medidas', [MemberController::class, 'guardarMedida'])->name('clientes.medidas.store');

    // Constructor de rutinas de ejercicio (rutina → día → ejercicio). Antes
    // vivía en /entrenador/rutinas; el módulo de Inscripciones (que se llama
    // "Rutinas" en el menú) se quedó con esa URL, así que el constructor se
    // movió a /entrenador/entrenamientos. Los NOMBRES de ruta ya coinciden
    // con la URL (entrenador.entrenamientos.*) — antes eran
    // entrenador.rutinas.*, cruzados con el módulo de Inscripciones (que
    // vive en /entrenador/rutinas), corregido el 14-08-2026 (ver /investigate).
    // dias.*/ejercicios.* quedan igual, nunca tuvieron el choque. Las
    // vistas usan route() por nombre, no por URL. Se llega a él desde la
    // ficha del cliente.
    Route::resource('entrenamientos', RoutineController::class)
        ->parameters(['entrenamientos' => 'routine'])
        ->names('entrenamientos');
    Route::post('entrenamientos/{routine}/dias', [RoutineController::class, 'agregarDia'])->name('entrenamientos.dias.store');
    Route::delete('dias/{routineDay}', [RoutineController::class, 'eliminarDia'])->name('dias.destroy');
    Route::post('dias/{routineDay}/ejercicios', [RoutineController::class, 'agregarEjercicio'])->name('dias.ejercicios.store');
    Route::delete('ejercicios-rutina/{routineExercise}', [RoutineController::class, 'eliminarEjercicio'])->name('entrenamientos.ejercicios.destroy');

    // Inscripciones (matricular clientes): mismo trámite guiado que usa
    // recepción, sin las pantallas de configuración que un entrenador no
    // necesita. Vive en /entrenador/rutinas, que es como se llama el enlace
    // del menú.
    Route::middleware('permiso:clientes.crear')->group(function () {
        Route::get('rutinas', [InscripcionController::class, 'index'])->name('inscripciones.index');
        Route::get('rutinas/buscar', [InscripcionController::class, 'buscar'])->name('inscripciones.buscar');
        Route::post('rutinas', [InscripcionController::class, 'store'])->name('inscripciones.store');
    });

    Route::middleware('permiso:inscripciones.editar')->group(function () {
        Route::put('rutinas/{membership}', [InscripcionController::class, 'update'])->name('inscripciones.update');
    });

    // Asistencia, con dos caras: "Mi marcación" es el fichaje laboral propio
    // (StaffAttendance: turno + entrada/salida) y "Mis clientes" es el
    // calendario de asistencias de clientes que el propio entrenador registró
    // (Attendance con registered_by = él). Las rutas de texto van antes que
    // las de {attendance} para que el binding de modelo no capture a los
    // clientes. Las vistas las ve quien tiene asistencia.ver; registrar o
    // corregir exige asistencia.registrar (ambas ya asignadas al rol).
    Route::get('asistencia', [AttendanceController::class, 'calendario'])
        ->name('asistencia.calendario')->middleware('permiso:asistencia.ver');
    Route::get('asistencia/mi-marcacion', [AttendanceController::class, 'miMarcacion'])
        ->name('asistencia.mi-marcacion')->middleware('permiso:asistencia.ver');
    Route::get('asistencia/clientes', [AttendanceController::class, 'buscarClientes'])
        ->name('asistencia.clientes')->middleware('permiso:asistencia.ver');
    // Escaneo QR: `estado` alimenta el modal y `qr` registra la marcación.
    // Rutas de texto ANTES de las {attendance}/{marcacion} de abajo. El POST
    // lleva throttle además de la guarda de 30 s del servicio, contra abuso.
    Route::get('asistencia/estado', [AttendanceController::class, 'estado'])
        ->name('asistencia.estado')->middleware('permiso:asistencia.ver');
    Route::post('asistencia/qr', [AttendanceController::class, 'marcarPorQr'])
        ->name('asistencia.qr')->middleware('permiso:asistencia.registrar', 'throttle:20,1');
    Route::post('asistencia/marcar', [AttendanceController::class, 'marcar'])
        ->name('asistencia.marcar')->middleware('permiso:asistencia.registrar');
    Route::post('asistencia/registrar', [AttendanceController::class, 'registrarEntrada'])
        ->name('asistencia.registrar')->middleware('permiso:asistencia.registrar');
    Route::post('asistencia/{attendance}/salida', [AttendanceController::class, 'marcarSalida'])
        ->name('asistencia.salida')->middleware('permiso:asistencia.registrar');
    Route::post('asistencia/{attendance}/solicitar-correccion', [AttendanceController::class, 'solicitarEdicionCliente'])
        ->name('asistencia.solicitar-correccion')->middleware('permiso:asistencia.registrar');
    Route::delete('asistencia/marcacion/{marcacion}', [AttendanceController::class, 'borrar'])
        ->name('asistencia.eliminar')->middleware('permiso:asistencia.registrar');
    Route::post('asistencia/marcacion/{marcacion}/solicitar-edicion', [AttendanceController::class, 'solicitarEdicion'])
        ->name('asistencia.solicitar-edicion')->middleware('permiso:asistencia.registrar');
    Route::get('asistencia/marcacion/{marcacion}/detalle', [AttendanceController::class, 'detalle'])
        ->name('asistencia.detalle')->middleware('permiso:asistencia.ver')
        ->whereNumber('marcacion');

    // Venta de mostrador (agua, bebidas, ropa): descuenta stock, no lo
    // gestiona — dar de alta productos o ajustar stock a mano sigue siendo
    // solo de admin (permiso inventario.gestionar, que el entrenador no tiene).
    Route::middleware('permiso:ventas.registrar')->group(function () {
        Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
        Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    });

    // Editar ventas propias (nombre, monto, método, etc.)
    Route::middleware('permiso:ventas.editar')->group(function () {
        Route::get('ventas/{venta}/edit', [VentaController::class, 'edit'])->name('ventas.edit');
        Route::put('ventas/{venta}', [VentaController::class, 'update'])->name('ventas.update');
        Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular');
    });
});
