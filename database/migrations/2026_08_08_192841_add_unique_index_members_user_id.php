<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saneo: si dos members quedaron apuntando a la misma cuenta, se
        // desenlaza el más viejo (se queda el más reciente) para que el
        // índice único no rompa la migración.
        $duplicados = DB::table('members')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicados as $userId) {
            $conservar = DB::table('members')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->value('id');

            DB::table('members')
                ->where('user_id', $userId)
                ->where('id', '!=', $conservar)
                ->update(['user_id' => null]);
        }

        Schema::table('members', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
