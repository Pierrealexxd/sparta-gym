<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario y ventas de mostrador (suplementos, accesorios).
 *
 * `products.stock` es un valor derivado que se mantiene al día por comodidad
 * de consulta, pero la verdad está en `stock_movements`: cualquier
 * descuadre se reconstruye sumando el histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('sku', 40)->nullable();
            $table->enum('category', ['suplemento', 'proteina', 'accesorio', 'bebida', 'ropa', 'otro'])
                  ->default('suplemento');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2);
            $table->integer('stock')->default(0);
            $table->unsignedSmallInteger('min_stock')->default(0);   // umbral de alerta

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gym_id', 'sku']);
            $table->index(['gym_id', 'category']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('type', ['entrada', 'salida', 'ajuste']);
            $table->integer('quantity');                  // con signo en los ajustes
            $table->integer('stock_after');               // foto del stock tras el movimiento
            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');          // venta, merma, compra…

            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number', 30);                 // correlativo por gimnasio
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('method', ['efectivo', 'transferencia', 'yape', 'plin', 'tarjeta', 'otro'])
                  ->default('efectivo');
            $table->enum('status', ['completada', 'anulada'])->default('completada');
            $table->dateTime('sold_at');

            $table->timestamps();

            $table->unique(['gym_id', 'number']);
            $table->index(['gym_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name');               // congelado, como en memberships
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
    }
};
