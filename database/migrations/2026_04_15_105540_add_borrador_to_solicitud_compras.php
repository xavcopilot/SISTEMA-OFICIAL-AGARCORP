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
        Schema::table('solicitud_compras', function (Blueprint $table) {
            $table->string('tipo_solicitud')->nullable()->change();
            $table->string('departamento_solicitante')->nullable()->change();
            $table->string('estado')->default('EN_ESPERA_DE_COTIZACION')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_compras', function (Blueprint $table) {
            $table->enum('tipo_solicitud', ['Consumo', 'Repuesto', 'Servicio'])->nullable(false)->change();
            $table->string('departamento_solicitante')->nullable(false)->change();
            $table->enum('estado', [
                'RECHAZADA',
                'EN_ESPERA_DE_COTIZACION',
                'SUMARIO_EN_REVISION',
                'OC_PENDIENTE_APROBACION',
                'ORDEN_APROBADA',
                'PAGADO',
                'EN_CREDITO',
                'MATERIAL_RECIBIDO',
                'CERRADA',
            ])->default('EN_ESPERA_DE_COTIZACION')->change();
        });
    }
};
