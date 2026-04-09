<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orden_compra_items', function (Blueprint $table) {
            $table->enum('estado_recepcion', [
                'PENDIENTE_RECEPCION',
                'ZONA_TRANSICION',
                'ENTREGADO_SOLICITANTE',
            ])->default('PENDIENTE_RECEPCION')->after('precio_total');

            $table->timestamp('en_transicion_at')->nullable()->after('estado_recepcion');
            $table->timestamp('entregado_at')->nullable()->after('en_transicion_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_compra_items', function (Blueprint $table) {
            $table->dropColumn([
                'estado_recepcion',
                'en_transicion_at',
                'entregado_at',
            ]);
        });
    }
};
