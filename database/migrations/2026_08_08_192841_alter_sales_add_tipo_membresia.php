<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('sale_type', ['producto', 'membresia', 'servicio', 'otro'])
                  ->default('producto')->after('member_id');
            $table->foreignId('membership_id')->nullable()->after('sale_type')
                  ->constrained()->nullOnDelete();
            $table->string('concept')->nullable()->after('total');
            $table->string('reference')->nullable()->after('concept');
            $table->text('notes')->nullable()->after('reference');

            $table->index(['gym_id', 'sale_type', 'sold_at']);
        });

        DB::statement("ALTER TABLE sales MODIFY method ENUM('efectivo','yape','plin','transferencia','tarjeta','otro') NOT NULL DEFAULT 'efectivo'");
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'sale_type', 'sold_at']);
            $table->dropConstrainedForeignId('membership_id');
            $table->dropColumn(['sale_type', 'concept', 'reference', 'notes']);
        });

        DB::statement("ALTER TABLE sales MODIFY method ENUM('efectivo','transferencia','yape','plin','tarjeta','otro') NOT NULL DEFAULT 'efectivo'");
    }
};
