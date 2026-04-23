<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('solicitud_compras')
            ->where('estado', 'EN_ESPERA_DE_COTIZACION')
            ->update(['estado' => 'EN_ESPERA_ALMACEN']);

        DB::table('solicitud_compras')
            ->where('estado', 'SUMARIO_EN_REVISION')
            ->update(['estado' => 'RECIBIDO_POR_PROCURA']);

        DB::table('solicitud_compras')
            ->whereIn('estado', ['OC_PENDIENTE_APROBACION', 'ORDEN_APROBADA', 'PAGADO', 'EN_CREDITO', 'MATERIAL_RECIBIDO', 'CERRADA'])
            ->update(['estado' => 'RECIBIDO_POR_PROCURA']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('solicitud_compras')
            ->where('estado', 'EN_ESPERA_ALMACEN')
            ->update(['estado' => 'EN_ESPERA_DE_COTIZACION']);

        DB::table('solicitud_compras')
            ->where('estado', 'RECIBIDO_POR_PROCURA')
            ->update(['estado' => 'SUMARIO_EN_REVISION']);
    }
};
