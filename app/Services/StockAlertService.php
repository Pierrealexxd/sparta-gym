<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAlert;
use App\Services\NotificationService;

/**
 * Alertas del inventario: mantiene una fila en `stock_alerts` por cada
 * producto que pide atención y ninguna más (una por producto, se resuelve
 * sola al reponer — no se acumulan duplicados).
 *
 * La semántica de los estados vive en Product (estado_stock / enEstado):
 * "por agotarse" es min_stock (umbral por producto), "stock bajo" es la banda
 * configurable min_stock × sparta.stock_umbral_bajo. Entrar en "bajo" o peor
 * dispara la alerta; volver a "normal" la resuelve.
 */
class StockAlertService
{
    /**
     * Reevalúa el producto y deja la alerta sincronizada: crea/actualiza si
     * está en "bajo" o peor, la borra si volvió a "normal". La unicidad por
     * producto (ver migración) hace imposible acumular duplicados.
     */
    public function evaluar(Product $p): void
    {
        if (! $p->is_active) {
            StockAlert::where('product_id', $p->id)->delete();
            $this->resolverNotificaciones($p);

            return;
        }

        $estado = $p->estado_stock;

        if ($estado === 'normal') {
            StockAlert::where('product_id', $p->id)->delete();
            $this->resolverNotificaciones($p);

            return;
        }

        StockAlert::updateOrCreate(
            ['product_id' => $p->id],
            [
                'gym_id' => $p->gym_id,
                'type'   => $estado === 'agotado' ? 'agotado' : 'bajo',
            ],
        );

        $this->notificar($p, $estado);
    }

    /**
     * Aviso por la campanita al staff de la sede. El dedupe por producto
     * del servicio evita duplicados: si la alerta ya existía sin leer, la
     * fila se refresca y el cursor del polling no la vuelve a toastear.
     */
    private function notificar(Product $p, string $estado): void
    {
        if (! NotificationService::enContextoWeb()) {
            return;
        }

        $servicio = app(NotificationService::class);
        $agotado  = $estado === 'agotado';

        $servicio->dispararA(
            $servicio->staffDeSede($p->gym_id),
            $agotado ? 'stock.agotado' : 'stock.bajo',
            $agotado ? 'Producto agotado' : 'Stock bajo',
            $agotado
                ? "{$p->name}: agotado"
                : "{$p->name}: quedan {$p->stock} · min {$p->min_stock}",
            'caja',
            $agotado ? 'alta' : 'media',
            $p->id,
            route('admin.inventario.index'),
        );
    }

    /** Al resolverse (repuesto o desactivado), la alerta sale del cajón. */
    private function resolverNotificaciones(Product $p): void
    {
        if (! NotificationService::enContextoWeb()) {
            return;
        }

        $servicio = app(NotificationService::class);
        $staff = $servicio->staffDeSede($p->gym_id);

        foreach (['stock.agotado', 'stock.bajo'] as $tipo) {
            foreach ($staff as $usuario) {
                $servicio->marcarLeidasDeTipo($usuario, $tipo, $p->id);
            }
        }
    }
}