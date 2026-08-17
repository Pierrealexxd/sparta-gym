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
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\ProgramRoutineController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\StockAlertController;
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

    // Vista Lista, alterna a Calendario con <x-alterna-vista>. Mismo
    // permiso que el calendario — no es un recurso nuevo, es otra forma
    // de ver los mismos datos.
    Route::get('asistencia/lista', [AttendanceController::class, 'lista'])
        ->name('asistencia.lista')->middleware('permiso:asistencia.ver');

    Route::get('asistencia/{marcacion}/detalle', [AttendanceController::class, 'detalle'])
        ->name('asistencia.detalle')->middleware('permiso:asistencia.ver')
        ->whereNumber('marcacion');

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
        // Previsualización inline: cada sección de la landing se renderiza
        // en un iframe dentro del panel, usando las mismas Blade views y
        // CSS que la página pública (ver PreviewController).
        Route::get('preview/{section}', [\App\Http\Controllers\Admin\PreviewController::class, 'show'])
            ->name('preview');

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

        // Programas (PLAN-PROGRAMAS.md): CRUD como modal, igual que FAQs y
        // testimonios — solo 'create'/'edit' quedan fuera porque no hay
        // páginas propias (ver corrección #3 de PROMPT-EJECUCION-PROGRAMAS.md).
        Route::resource('programas', ProgramController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['programas' => 'programa']);
        Route::post('programas/{programa}/publicar', [ProgramController::class, 'publicar'])
            ->name('programas.publicar');
        Route::post('programas/{programa}/ocultar', [ProgramController::class, 'ocultar'])
            ->name('programas.ocultar');

        // Rutinas base por programa: sí llevan páginas propias (create/edit),
        // el formulario con días/ejercicios anidados no cabe en un modal.
        // Días/ejercicios se agregan/quitan con sus propias rutas, mismo
        // patrón que entrenador.entrenamientos.dias.* / dias.ejercicios.* —
        // el plan pedía un único formulario Alpine con arrays anidados, pero
        // el código real de rutinas (entrenador/rutinas) no funciona así:
        // se editan una vez creada la rutina, día a día (ver corrección en
        // PROMPT-EJECUCION-PROGRAMAS.md, "el código real manda").
        Route::resource('programas.rutinas', ProgramRoutineController::class)
            ->except(['show'])
            ->parameters(['programas' => 'programa', 'rutinas' => 'rutina']);
        Route::post('programas/{programa}/rutinas/{rutina}/dias', [ProgramRoutineController::class, 'agregarDia'])
            ->name('programas.rutinas.dias.store');
        Route::delete('rutinas-base/dias/{dia}', [ProgramRoutineController::class, 'eliminarDia'])
            ->name('programas.rutinas.dias.destroy');
        Route::post('rutinas-base/dias/{dia}/ejercicios', [ProgramRoutineController::class, 'agregarEjercicio'])
            ->name('programas.rutinas.dias.ejercicios.store');
        Route::delete('rutinas-base/ejercicios/{ejercicio}', [ProgramRoutineController::class, 'eliminarEjercicio'])
            ->name('programas.rutinas.ejercicios.destroy');
    });

    Route::middleware('permiso:inventario.ver')->group(function () {
        Route::get('inventario', [ProductController::class, 'index'])->name('inventario.index');
        Route::get('inventario/create', [ProductController::class, 'create'])->name('inventario.create')->middleware('permiso:inventario.gestionar');
        Route::post('inventario', [ProductController::class, 'store'])->name('inventario.store')->middleware('permiso:inventario.gestionar');
        // Alertas para la campanita: literal y ANTES de "inventario/{producto}"
        // (show), para que "alertas" no lo capture como si fuera un id.
        Route::get('inventario/alertas', [StockAlertController::class, 'pendientesJson'])->name('inventario.alertas');
        Route::get('inventario/{producto}', [ProductController::class, 'show'])->name('inventario.show');
        Route::get('inventario/{producto}/edit', [ProductController::class, 'edit'])->name('inventario.edit')->middleware('permiso:inventario.gestionar');
        Route::put('inventario/{producto}', [ProductController::class, 'update'])->name('inventario.update')->middleware('permiso:inventario.gestionar');
        // "inventario/masivo" va antes de "inventario/{producto}": DELETE con
        // segmento literal debe ganarle al wildcard (mismo patrón que clientes).
        Route::delete('inventario/masivo', [ProductController::class, 'destroyMasivo'])->name('inventario.masivo')->middleware('permiso:inventario.gestionar');
        Route::delete('inventario/{producto}', [ProductController::class, 'destroy'])->name('inventario.destroy')->middleware('permiso:inventario.gestionar');
        Route::post('inventario/{producto}/movimiento', [ProductController::class, 'registrarMovimiento'])->name('inventario.movimiento')->middleware('permiso:inventario.gestionar');
    });

    Route::get('ventas', [SaleController::class, 'index'])->name('ventas.index')->middleware('permiso:inventario.ver');
    Route::post('ventas', [SaleController::class, 'store'])->name('ventas.store')->middleware('permiso:ventas.registrar');
    Route::post('ventas/{venta}/anular', [SaleController::class, 'anular'])
        ->name('ventas.anular')->middleware('permiso:pagos.anular');
    Route::get('ventas/buscar-cliente', [SaleController::class, 'buscarCliente'])
        ->name('ventas.buscar-cliente')->middleware('permiso:ventas.registrar');
    // Antes de "ventas/{venta}/anular" en el archivo no importa (Laravel
    // matchea por método+segmento literal primero), pero "exportar" e
    // "importar" son literales fijos, así que no chocan con {venta}.
    Route::get('ventas/exportar', [SaleController::class, 'exportar'])
        ->name('ventas.exportar')->middleware('permiso:reportes.exportar');
    Route::post('ventas/importar', [SaleController::class, 'importar'])
        ->name('ventas.importar')->middleware('permiso:reportes.exportar');

    Route::get('usuarios/buscar-clientes', [UserController::class, 'buscarClientes'])
        ->name('usuarios.buscar-clientes')
        ->middleware('permiso:usuarios.gestionar');
    Route::resource('usuarios', UserController::class)
        ->except(['show'])
        ->parameters(['usuarios' => 'usuario'])
        ->middleware('permiso:usuarios.gestionar');
});
