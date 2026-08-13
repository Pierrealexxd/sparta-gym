<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceEditRequestController;
use App\Http\Controllers\Admin\ContactoController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExerciseController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\GymController;
use App\Http\Controllers\Admin\GymQrCodeController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MatriculaController;
use App\Http\Controllers\Admin\MembershipController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\TrainerController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel de administración y recepción
|--------------------------------------------------------------------------
| Administrador y recepción comparten la misma puerta; dentro, cada acción
| sensible además exige su permiso concreto (ver EnsurePermission).
*/

Route::prefix('admin')->name('admin.')->middleware('rol:admin,recepcion')->group(function () {

    Route::get('/', DashboardController::class)->name('dashboard');

    // Antes del resource: "clientes/buscar" es literal y tiene que ganarle
    // a "clientes/{member}" (show), que si no la captura como si "buscar"
    // fuera un id.
    Route::get('clientes/buscar', [MemberController::class, 'buscar'])->name('clientes.buscar');
    // 'create' fuera: "Nuevo cliente" vive como modal de admin.clientes.index
    // (ver más abajo) en vez de pantalla propia — igual que ya hicimos con
    // matrícula e inscripción.
    Route::resource('clientes', MemberController::class)
        ->except(['destroy', 'create'])
        ->parameters(['clientes' => 'member']);
    // El borrado exige su propio permiso: recepción lista y crea, pero no
    // puede eliminar el expediente de un cliente.
    Route::delete('clientes/masivo', [MemberController::class, 'destroyMasivo'])
        ->name('clientes.masivo')
        ->middleware('permiso:clientes.eliminar');
    Route::delete('clientes/{member}', [MemberController::class, 'destroy'])
        ->name('clientes.destroy')
        ->middleware('permiso:clientes.eliminar');
    Route::post('clientes/{member}/medidas', [MemberController::class, 'guardarMedida'])->name('clientes.medidas.store');
    Route::post('clientes/{member}/objetivos', [MemberController::class, 'guardarObjetivo'])->name('clientes.objetivos.store');
    Route::get('clientes/{member}/carnet', [MemberController::class, 'carnet'])->name('clientes.carnet');

    // El wizard vive como modal dentro de Clientes (admin.clientes.index) en
    // vez de pantalla propia — solo queda el POST que procesa el envío.
    Route::post('matricula', [MatriculaController::class, 'store'])
        ->name('matricula.store')->middleware('permiso:clientes.crear');

    Route::get('membresias', [MembershipController::class, 'index'])->name('membresias.index');
    // Nombre "clientes.membresias.store" (no "membresias.store" a secas):
    // la URL vive anidada bajo clientes, no bajo /admin/membresias — antes
    // el nombre no lo reflejaba y confundía con el módulo de arriba
    // (ver /investigate del 14-08-2026).
    Route::post('clientes/{member}/membresias', [MembershipController::class, 'store'])->name('clientes.membresias.store');
    Route::post('membresias/{membership}/cancelar', [MembershipController::class, 'cancelar'])->name('membresias.cancelar');

// Módulo único "Asistencia": el calendario de marcaciones LABORALES del
    // staff (antes vivía en la pestaña aparte "Personal"; el calendario de
    // entradas de clientes y el registro por torno se dieron de baja — ver
    // decisión del 13-08-2026) y solicitudes de corrección, como pestañas de
    // la misma pantalla — ver admin/asistencia/_pestanas.blade.php.
    Route::get('asistencia/calendario', [AttendanceController::class, 'calendario'])
        ->name('asistencia.calendario')->middleware('permiso:asistencia.ver');

    Route::middleware('permiso:asistencia.aprobar')->prefix('asistencia/solicitudes')->name('asistencia.solicitudes.')->group(function () {
        Route::get('/', [AttendanceEditRequestController::class, 'index'])->name('index');
        Route::get('pendientes.json', [AttendanceEditRequestController::class, 'pendientesJson'])->name('pendientes-json');
        Route::post('{solicitud}/aprobar', [AttendanceEditRequestController::class, 'aprobar'])->name('aprobar');
        Route::post('{solicitud}/rechazar', [AttendanceEditRequestController::class, 'rechazar'])->name('rechazar');
    });

    Route::resource('ejercicios', ExerciseController::class)
        ->except(['show'])
        ->parameters(['ejercicios' => 'ejercicio'])
        ->middleware('permiso:ejercicios.gestionar');
    Route::post('ejercicios/{ejercicio}/publicar', [ExerciseController::class, 'publicar'])
        ->name('ejercicios.publicar')
        ->middleware('permiso:ejercicios.gestionar');
    Route::post('ejercicios/{ejercicio}/ocultar', [ExerciseController::class, 'ocultar'])
        ->name('ejercicios.ocultar')
        ->middleware('permiso:ejercicios.gestionar');

    // Fase 3 del plan de nutrición: biblioteca de recetas, solo
    // administración (a diferencia de ejercicios, que también gestiona el
    // entrenador — ver la decisión registrada en PLAN_NUTRICION_PROGRESO.md).
    Route::resource('recetas', RecipeController::class)
        ->except(['show'])
        ->parameters(['recetas' => 'receta'])
        ->middleware('permiso:recetas.gestionar');
    Route::post('recetas/{receta}/publicar', [RecipeController::class, 'publicar'])
        ->name('recetas.publicar')
        ->middleware('permiso:recetas.gestionar');
    Route::post('recetas/{receta}/ocultar', [RecipeController::class, 'ocultar'])
        ->name('recetas.ocultar')
        ->middleware('permiso:recetas.gestionar');

    // ->parameters() alinea el nombre del parámetro de ruta con el que
    // esperan los métodos del controlador ($entrenador, $plan): el plural
    // "entrenadores"/"planes" singulariza mal (entrenadore, plane) y el
    // enlace implícito de modelo sólo funciona si los nombres coinciden.
    Route::resource('entrenadores', TrainerController::class)
        ->except(['show'])
        ->parameters(['entrenadores' => 'entrenador'])
        ->middleware('permiso:entrenadores.gestionar');

    Route::resource('planes', PlanController::class)
        ->except(['show'])
        ->parameters(['planes' => 'plan'])
        ->middleware('permiso:planes.gestionar');

    Route::resource('sedes', GymController::class)
        ->except(['show'])
        ->parameters(['sedes' => 'sede'])
        ->middleware('permiso:sedes.gestionar');

    // QR de asistencia laboral por sede: generar/rotar e imprimir. Mismo
    // permiso que el resto del recurso sedes. La rotación (regenerar) se
    // confirma en la vista porque invalida el QR físico anterior.
    Route::middleware('permiso:sedes.gestionar')->group(function () {
        Route::get('sedes/{sede}/qr', [GymQrCodeController::class, 'mostrar'])->name('sedes.qr');
        Route::post('sedes/{sede}/qr/regenerar', [GymQrCodeController::class, 'regenerar'])->name('sedes.qr.regenerar');
        Route::get('sedes/{sede}/qr/imprimir', [GymQrCodeController::class, 'imprimir'])->name('sedes.qr.imprimir');
    });

    Route::middleware('permiso:web.editar')->group(function () {
        Route::resource('faqs', FaqController::class)->except(['show']);
        Route::post('faqs/{faq}/publicar', [FaqController::class, 'publicar'])
            ->name('faqs.publicar');
        Route::post('faqs/{faq}/ocultar', [FaqController::class, 'ocultar'])
            ->name('faqs.ocultar');
        Route::post('testimonios/{testimonio}/publicar', [TestimonialController::class, 'publicar'])
            ->name('testimonios.publicar');
        Route::post('testimonios/{testimonio}/ocultar', [TestimonialController::class, 'ocultar'])
            ->name('testimonios.ocultar');
        Route::resource('testimonios', TestimonialController::class)->except(['show']);

        // Contacto de la página pública: formulario de una sola instancia que
        // edita el gimnasio activo (el que sirve la landing). Sin recurso CRUD.
        Route::get('contenido/contacto', [ContactoController::class, 'editar'])
            ->name('contenido.contacto');
        Route::post('contenido/contacto', [ContactoController::class, 'guardar'])
            ->name('contenido.contacto.guardar');
    });

    Route::middleware('permiso:inventario.ver')->group(function () {
        Route::get('inventario', [ProductController::class, 'index'])->name('inventario.index');
        Route::get('inventario/create', [ProductController::class, 'create'])->name('inventario.create')->middleware('permiso:inventario.gestionar');
        Route::post('inventario', [ProductController::class, 'store'])->name('inventario.store')->middleware('permiso:inventario.gestionar');
        Route::get('inventario/{producto}/edit', [ProductController::class, 'edit'])->name('inventario.edit')->middleware('permiso:inventario.gestionar');
        Route::put('inventario/{producto}', [ProductController::class, 'update'])->name('inventario.update')->middleware('permiso:inventario.gestionar');
        Route::delete('inventario/{producto}', [ProductController::class, 'destroy'])->name('inventario.destroy')->middleware('permiso:inventario.gestionar');
        Route::post('inventario/{producto}/movimiento', [ProductController::class, 'registrarMovimiento'])->name('inventario.movimiento')->middleware('permiso:inventario.gestionar');
    });

    Route::get('ventas', [SaleController::class, 'index'])->name('ventas.index')->middleware('permiso:inventario.ver');
    Route::post('ventas', [SaleController::class, 'store'])->name('ventas.store')->middleware('permiso:ventas.registrar');
    Route::post('ventas/{venta}/anular', [SaleController::class, 'anular'])
        ->name('ventas.anular')->middleware('permiso:pagos.anular');
    Route::get('ventas/buscar-cliente', [SaleController::class, 'buscarCliente'])
        ->name('ventas.buscar-cliente')->middleware('permiso:ventas.registrar');

    Route::get('usuarios/buscar-clientes', [UserController::class, 'buscarClientes'])
        ->name('usuarios.buscar-clientes')
        ->middleware('permiso:usuarios.gestionar');
    Route::resource('usuarios', UserController::class)
        ->except(['show'])
        ->parameters(['usuarios' => 'usuario'])
        ->middleware('permiso:usuarios.gestionar');
});
