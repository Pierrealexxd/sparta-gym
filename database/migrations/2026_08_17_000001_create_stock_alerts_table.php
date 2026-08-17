<?php

use App\Models\Product;
use App\Services\StockAlertService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas de stock pendientes, una por producto (la única es la vigente; al
 * resolverse se borra y el próximo episodio crea otra — así no hay duplicados
 * acumulándose). Alimenta el contador del sidebar y la campanita del panel;
 * se mantienen al día solas vía el observer de Product en AppServiceProvider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->unique();
            $table->enum('type', ['bajo', 'agotado'])->default('bajo');
            $table->timestamps();
        });

        // Backfill: un producto que ya esté bajo el umbral cuando se despliega
        // la tabla debe notificar desde ya, no esperar al próximo movimiento.
        foreach (Product::withTrashed()->get() as $producto) {
            app(StockAlertService::class)->evaluar($producto);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};