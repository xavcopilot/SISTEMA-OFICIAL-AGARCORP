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
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('workflow_post_compra', 80)
                ->default('PENDIENTE_PAGO_FINANZAS')
                ->after('estado');

            $table->timestamp('pago_registrado_at')->nullable()->after('workflow_post_compra');
            $table->foreignId('pago_por_user_id')->nullable()->after('pago_registrado_at')->constrained('users')->nullOnDelete();
            $table->string('comprobante_pago_path')->nullable()->after('pago_por_user_id');
            $table->string('referencia_pago')->nullable()->after('comprobante_pago_path');
            $table->decimal('monto_pagado', 14, 2)->nullable()->after('referencia_pago');
            $table->text('observacion_pago')->nullable()->after('monto_pagado');

            $table->timestamp('confirmado_procura_at')->nullable()->after('observacion_pago');
            $table->foreignId('confirmado_por_user_id')->nullable()->after('confirmado_procura_at')->constrained('users')->nullOnDelete();

            $table->timestamp('factura_enviada_administracion_at')->nullable()->after('factura_path');
            $table->foreignId('factura_enviada_por_user_id')->nullable()->after('factura_enviada_administracion_at')->constrained('users')->nullOnDelete();

            $table->timestamp('devolucion_solicitada_at')->nullable()->after('conformidad_solicitante_at');
            $table->foreignId('devolucion_solicitada_por_user_id')->nullable()->after('devolucion_solicitada_at')->constrained('users')->nullOnDelete();
            $table->text('devolucion_motivo')->nullable()->after('devolucion_solicitada_por_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropConstrainedForeignId('devolucion_solicitada_por_user_id');
            $table->dropColumn('devolucion_solicitada_at');
            $table->dropColumn('devolucion_motivo');

            $table->dropConstrainedForeignId('factura_enviada_por_user_id');
            $table->dropColumn('factura_enviada_administracion_at');

            $table->dropConstrainedForeignId('confirmado_por_user_id');
            $table->dropColumn('confirmado_procura_at');

            $table->dropConstrainedForeignId('pago_por_user_id');
            $table->dropColumn('pago_registrado_at');
            $table->dropColumn('comprobante_pago_path');
            $table->dropColumn('referencia_pago');
            $table->dropColumn('monto_pagado');
            $table->dropColumn('observacion_pago');

            $table->dropColumn('workflow_post_compra');
        });
    }
};
