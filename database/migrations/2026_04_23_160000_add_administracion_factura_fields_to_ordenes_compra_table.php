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
            $table->string('factura_numero')->nullable()->after('factura_path');
            $table->string('factura_numero_control')->nullable()->after('factura_numero');
            $table->date('factura_fecha_emision')->nullable()->after('factura_numero_control');
            $table->decimal('factura_base_imponible', 14, 2)->nullable()->after('factura_fecha_emision');
            $table->decimal('factura_monto_iva', 14, 2)->nullable()->after('factura_base_imponible');
            $table->decimal('factura_monto_total', 14, 2)->nullable()->after('factura_monto_iva');
            $table->decimal('retencion_iva_monto', 14, 2)->nullable()->after('factura_monto_total');
            $table->decimal('retencion_islr_monto', 14, 2)->nullable()->after('retencion_iva_monto');
            $table->json('comprobantes_retencion_paths')->nullable()->after('retencion_islr_monto');
            $table->text('observacion_administracion')->nullable()->after('comprobantes_retencion_paths');
            $table->timestamp('factura_cargada_administracion_at')->nullable()->after('observacion_administracion');
            $table->foreignId('factura_cargada_por_user_id')->nullable()->after('factura_cargada_administracion_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factura_cargada_por_user_id');
            $table->dropColumn([
                'factura_numero',
                'factura_numero_control',
                'factura_fecha_emision',
                'factura_base_imponible',
                'factura_monto_iva',
                'factura_monto_total',
                'retencion_iva_monto',
                'retencion_islr_monto',
                'comprobantes_retencion_paths',
                'observacion_administracion',
                'factura_cargada_administracion_at',
            ]);
        });
    }
};
