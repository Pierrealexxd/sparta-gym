<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\SedeActivaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web pública
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('landing');

// El video del hero se sirve por esta ruta (no como archivo estático directo
// de /public) para que Laravel/Symfony respondan bien a "Range requests":
// Safari/iOS exige eso para reproducir video en absoluto, y menos aún en
// autoplay (ver LandingController::heroVideoStream).
Route::get('/videos/hero.mp4', [LandingController::class, 'heroVideoStream'])->name('landing.hero-video');

Route::post('/contacto', [LandingController::class, 'contactar'])
    ->middleware('throttle:6,1')          // un formulario de contacto no necesita más
    ->name('contacto.enviar');

/*
|--------------------------------------------------------------------------
| Acceso
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'entrar'])->middleware('throttle:10,1');

    // Alta pública: solo crea cuentas de cliente (rol cliente) — ver
    // RegisterController para por qué eso no se puede burlar desde aquí.
    Route::get('/registro', [RegisterController::class, 'mostrar'])->name('registro');
    Route::post('/registro', [RegisterController::class, 'registrar'])
        ->middleware('throttle:6,1')->name('registro.store');
});

Route::post('/salir', [LoginController::class, 'salir'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Paneles
|--------------------------------------------------------------------------
| Cada rol entra por su propia puerta, protegida por el middleware `rol`.
| Las rutas viven en un archivo por panel para que crecer uno no obligue
| a leer los otros dos.
*/

Route::middleware(['auth', 'sede.activa'])->group(function () {
    Route::post('/sede-activa', [SedeActivaController::class, 'store'])->name('sede.activar');

    /*
    |--------------------------------------------------------------------------
    | Compartidas: las usan los tres paneles
    |--------------------------------------------------------------------------
    | Perfil y mensajería no pertenecen a un rol: cualquier cuenta activa de la
    | sede entra. Por eso viven aquí, fuera de los grupos `rol:`.
    */

    Route::get('/mi-perfil', [PerfilController::class, 'mostrar'])->name('perfil');
    Route::post('/mi-perfil', [PerfilController::class, 'actualizar'])->name('perfil.actualizar');
    Route::post('/mi-perfil/password', [PerfilController::class, 'cambiarPassword'])->name('perfil.password');

    // Las rutas de texto (no-leidas, directorio) van antes de la de
    // {conversacion} para que el binding de modelo no las capture.
    Route::get('/mensajes', [MensajeController::class, 'index'])->name('mensajes');
    Route::get('/mensajes/lista', [MensajeController::class, 'lista'])->name('mensajes.lista');
    Route::get('/mensajes/no-leidas', [MensajeController::class, 'noLeidas'])->name('mensajes.no-leidas');
    Route::get('/mensajes/directorio', [MensajeController::class, 'directorio'])->name('mensajes.directorio');
    Route::post('/mensajes/conversacion', [MensajeController::class, 'conversar'])->name('mensajes.conversar');
    Route::get('/mensajes/{conversacion}/mensajes', [MensajeController::class, 'listaMensajes'])->name('mensajes.listaMensajes');
    Route::post('/mensajes/{conversacion}', [MensajeController::class, 'enviar'])->name('mensajes.enviar');

    require __DIR__ . '/admin.php';
    require __DIR__ . '/entrenador.php';
    require __DIR__ . '/cliente.php';
});
