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
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sumario_id')->constrained('sumarios')->cascadeOnDelete();

            $table->string('correlativo_odc')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('rif_proveedor')->nullable();
            $table->string('direccion_proveedor')->nullable();
            $table->string('email_proveedor')->nullable();
            $table->string('contacto_proveedor')->nullable();
            $table->decimal('tasa_bcv', 14, 6)->nullable();
            $table->string('condicion_pago')->nullable();
            $table->string('departamento_solicitante')->nullable();
            $table->string('sitio_entrega')->nullable();
            $table->text('comentarios')->nullable();
            $table->foreignId('elaborado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('elaborado_firmado_at')->nullable();
            $table->foreignId('aprobado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_firmado_at')->nullable();

            $table->decimal('monto_exento', 14, 2)->default(0);
            $table->decimal('sub_total', 14, 2)->default(0);
            $table->decimal('iva_16', 14, 2)->default(0);
            $table->decimal('gastos_adicionales', 14, 2)->default(0);
            $table->decimal('total_general', 14, 2)->default(0);

            $table->string('estado', 80)->default('PENDIENTE_APROBACION');
            $table->string('workflow_post_compra', 80)->default('PENDIENTE_PAGO_FINANZAS');
            $table->timestamp('pago_registrado_at')->nullable();
            $table->foreignId('pago_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('comprobante_pago_path')->nullable();
            $table->string('referencia_pago')->nullable();
            $table->decimal('monto_pagado', 14, 2)->nullable();
            $table->text('observacion_pago')->nullable();
            $table->timestamp('confirmado_procura_at')->nullable();
            $table->foreignId('confirmado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo_documento_recepcion', 20)->nullable();
            $table->string('factura_path')->nullable();
            $table->string('nota_entrega_path')->nullable();
            $table->string('factura_numero')->nullable();
            $table->string('factura_numero_control')->nullable();
            $table->date('factura_fecha_emision')->nullable();
            $table->decimal('factura_base_imponible', 14, 2)->nullable();
            $table->decimal('factura_monto_iva', 14, 2)->nullable();
            $table->decimal('factura_monto_total', 14, 2)->nullable();
            $table->decimal('retencion_iva_monto', 14, 2)->nullable();
            $table->decimal('retencion_islr_monto', 14, 2)->nullable();
            $table->json('comprobantes_retencion_paths')->nullable();
            $table->text('observacion_administracion')->nullable();
            $table->timestamp('factura_cargada_administracion_at')->nullable();
            $table->foreignId('factura_cargada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('factura_enviada_administracion_at')->nullable();
            $table->foreignId('factura_enviada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('factura_pendiente')->default(false);
            $table->timestamp('recepcion_procesada_at')->nullable();
            $table->foreignId('recibido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conformidad_solicitante_at')->nullable();
            $table->foreignId('conformidad_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('devolucion_solicitada_at')->nullable();
            $table->foreignId('devolucion_solicitada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('devolucion_motivo')->nullable();
            $table->foreignId('inventario_movimiento_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->timestamp('factura_procesada_administracion_at')->nullable();
            $table->string('rechazo_etapa')->nullable();
            $table->text('rechazo_comentario')->nullable();
            $table->foreignId('rechazo_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rechazo_en')->nullable();

            $table->timestamps();
        });

        Schema::create('orden_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('sumario_item_id')->nullable()->constrained('sumario_items')->nullOnDelete();
            $table->foreignId('solicitud_compra_item_id')->constrained('solicitud_compra_items')->restrictOnDelete();

            $table->unsignedInteger('item')->nullable();
            $table->string('descripcion');
            $table->string('unidad_medida', 20)->default('UND');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 14, 2)->default(0);
            $table->decimal('precio_total', 14, 2)->default(0);
            $table->string('estado_recepcion', 40)->default('PENDIENTE_RECEPCION');
            $table->timestamp('en_transicion_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->string('decision_solicitante', 20)->nullable();
            $table->text('motivo_rechazo_solicitante')->nullable();
            $table->timestamp('conformidad_solicitante_at')->nullable();
            $table->timestamp('procesado_almacen_at')->nullable();
            $table->string('modo_ingreso_almacen', 30)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_compra_items');
        Schema::dropIfExists('ordenes_compra');
    }
};
