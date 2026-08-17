<?php

namespace App\Providers;

use App\Models\ContactMessage;
use App\Models\Message;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Testimonial;
use App\Services\NotificationService;
use App\Services\StockAlertService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La vista por defecto de Laravel usa Tailwind, que aquí no existe;
        // la del panel está en resources/views/vendor/pagination/panel.blade.php.
        Paginator::defaultView('pagination::panel');

        // Toda escritura de stock pasa por Product::update(['stock' => ...]) o
        // Product::create (stock es un saldo, ver AGENTS.md), así que este
        // único hook mantiene las alertas de inventario al día en cada punto
        // de venta/movimiento sin tocar los controladores. Cambiar min_stock
        // o desactivar el producto también reevalúa.
        // Nota: wasChanged() es falso durante el saved de un create (Laravel
        // setea $changes tras disparar el evento), por eso wasRecentlyCreated
        // se comprueba aparte — un producto nuevo con stock inicial bajo el
        // umbral debe notificar desde ya.
        Product::saved(function (Product $producto) {
            if ($producto->wasRecentlyCreated
                || $producto->wasChanged('stock')
                || $producto->wasChanged('min_stock')
                || $producto->wasChanged('is_active')) {
                app(StockAlertService::class)->evaluar($producto);
            }
        });

        // Eventos que generan notificaciones (ver plan-notificaciones-toast.md):
        // observers para eventos puros de modelo, llamadas explícitas al
        // servicio en los flujos ya centralizados (stock, asistencia,
        // matrícula). Regla común: el actor no recibe su propia notificación
        // (su feedback es el toast flash), y los seeds/CLI no notifican.
        $this->observersDeNotificaciones();
    }

    /**
     * Observers que avisan por notificación los eventos de otros módulos.
     * Separados en su propio método para que boot() siga siendo legible.
     */
    private function observersDeNotificaciones(): void
    {
        $notificaciones = fn () => app(NotificationService::class);

        // Un mensaje nuevo avisa al otro participante del hilo. El emisor ya
        // tiene su confirmación en pantalla; no necesita notificación.
        Message::created(function (Message $mensaje) use ($notificaciones) {
            if (! NotificationService::enContextoWeb()) {
                return;
            }

            $conversacion = $mensaje->conversation()->with('participants.user')->first();
            $receptor = $conversacion?->participants
                ->map->user
                ->first(fn ($u) => $u->id !== $mensaje->sender_id);

            if (! $receptor || ! $receptor->is_active) {
                return;
            }

            $notificaciones()->disparar(
                $receptor,
                'mensaje.nuevo',
                'Nuevo mensaje de ' . ($mensaje->sender?->name ?? 'un compañero'),
                mb_strimwidth($mensaje->body, 0, 90, '…'),
                'chat',
                'media',
                $conversacion->id,
                route('mensajes') . '?con=' . $conversacion->id,
            );
        });

        // Venta de mostrador: el staff de la sede se entera (salvo quien la
        // registró). Las ventas de membresía nacen de MatriculaService, que
        // avisa por su cuenta — acá solo 'producto'.
        Sale::created(function (Sale $venta) use ($notificaciones) {
            if (! NotificationService::enContextoWeb() || $venta->sale_type !== 'producto') {
                return;
            }

            $servicio = $notificaciones();
            $servicio->dispararA(
                $servicio->staffDeSede($venta->gym_id, $venta->sold_by),
                'venta.nueva',
                'Venta registrada',
                "Venta {$venta->number} · S/ {$venta->total}",
                'billetera',
                'media',
                $venta->id,
                route('admin.ventas.index'),
            );
        });

        // Mensaje del formulario de contacto de la web: los admins se
        // enteran en el momento (el visitante ya vio su confirmación).
        ContactMessage::created(function (ContactMessage $mensaje) use ($notificaciones) {
            if (! NotificationService::enContextoWeb()) {
                return;
            }

            $servicio = $notificaciones();
            $servicio->dispararA(
                $servicio->staffDeSede($mensaje->gym_id),
                'contacto.nuevo',
                'Mensaje de contacto',
                $mensaje->name . ': ' . mb_strimwidth($mensaje->message, 0, 70, '…'),
                'correo',
                'media',
                $mensaje->id,
                route('admin.contenido.contacto'),
            );
        });

        // Reseña escrita por un socio desde su panel: queda a la espera de
        // aprobación y el staff se entera (solo si tiene autor, es decir si
        // vino del panel del cliente, no de la carga manual del admin).
        Testimonial::created(function (Testimonial $testimonio) use ($notificaciones) {
            if (! NotificationService::enContextoWeb() || $testimonio->member_id === null) {
                return;
            }

            $servicio = $notificaciones();
            $servicio->dispararA(
                $servicio->staffDeSede($testimonio->gym_id),
                'resena.nueva',
                'Nueva reseña',
                mb_strimwidth($testimonio->content, 0, 70, '…'),
                'estrella',
                'baja',
                $testimonio->id,
                route('admin.testimonios.index'),
            );
        });
    }
}
