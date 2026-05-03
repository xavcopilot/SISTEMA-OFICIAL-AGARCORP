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
        Schema::create('pbi_roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->timestamps();
        });

        Schema::create('pbi_departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->timestamps();
        });

        Schema::create('pbi_usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('email')->unique();
            $table->foreignId('rol_id')->constrained('pbi_roles')->restrictOnDelete();
            $table->foreignId('departamento_id')->constrained('pbi_departamentos')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pbi_proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('rif', 30)->nullable()->unique();
            $table->string('nombre', 160);
            $table->string('categoria', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pbi_categorias_producto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->timestamps();
        });

        Schema::create('pbi_productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 180);
            $table->foreignId('categoria_id')->constrained('pbi_categorias_producto')->restrictOnDelete();
            $table->string('unidad_medida', 20)->default('UND');
            $table->decimal('costo_referencia', 14, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pbi_solicitudes_compra', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->date('fecha_solicitud')->index();
            $table->foreignId('solicitante_user_id')->constrained('pbi_usuarios')->restrictOnDelete();
            $table->foreignId('departamento_solicitante_id')->constrained('pbi_departamentos')->restrictOnDelete();
            $table->string('prioridad', 20)->default('MEDIA');
            $table->string('estado', 40)->default('REGISTRADA')->index();
            $table->decimal('monto_estimado_total', 14, 2)->default(0);
            $table->date('fecha_requerida')->nullable();
            $table->timestamp('aprobada_almacen_at')->nullable();
            $table->foreignId('aprobada_almacen_por_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pbi_solicitud_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_compra_id')->constrained('pbi_solicitudes_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('pbi_productos')->nullOnDelete();
            $table->string('descripcion', 200);
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_estimado_unitario', 14, 2)->default(0);
            $table->decimal('subtotal_estimado', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pbi_sumarios_cotizacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('solicitud_compra_id')->constrained('pbi_solicitudes_compra')->cascadeOnDelete();
            $table->foreignId('analista_procura_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->date('fecha_sumario')->index();
            $table->string('estado', 40)->default('EN_ANALISIS')->index();
            $table->decimal('monto_referencial_total', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('pbi_sumario_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sumario_cotizacion_id')->constrained('pbi_sumarios_cotizacion')->cascadeOnDelete();
            $table->foreignId('solicitud_item_id')->nullable()->constrained('pbi_solicitud_items')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('pbi_productos')->nullOnDelete();
            $table->decimal('cantidad_cotizada', 12, 2);
            $table->decimal('precio_referencial', 14, 2)->default(0);
            $table->decimal('subtotal_referencial', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pbi_ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('sumario_cotizacion_id')->constrained('pbi_sumarios_cotizacion')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('pbi_proveedores')->restrictOnDelete();
            $table->foreignId('comprador_procura_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->date('fecha_emision')->index();
            $table->date('fecha_compromiso_entrega')->nullable();
            $table->date('fecha_entrega_real')->nullable();
            $table->string('estado', 40)->default('EMITIDA')->index();
            $table->decimal('monto_subtotal', 14, 2)->default(0);
            $table->decimal('monto_impuestos', 14, 2)->default(0);
            $table->decimal('monto_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pbi_orden_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('pbi_ordenes_compra')->cascadeOnDelete();
            $table->foreignId('sumario_item_id')->nullable()->constrained('pbi_sumario_items')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('pbi_productos')->nullOnDelete();
            $table->decimal('cantidad_ordenada', 12, 2);
            $table->decimal('precio_unitario', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pbi_pagos_finanzas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('pbi_ordenes_compra')->cascadeOnDelete();
            $table->date('fecha_programada_pago')->nullable()->index();
            $table->date('fecha_pago')->nullable()->index();
            $table->foreignId('pagado_por_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->string('estado_pago', 40)->default('PROGRAMADO')->index();
            $table->string('metodo_pago', 40)->nullable();
            $table->string('referencia_pago', 100)->nullable();
            $table->decimal('monto_pagado', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pbi_recepciones_procura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('pbi_ordenes_compra')->cascadeOnDelete();
            $table->date('fecha_recepcion_procura')->index();
            $table->foreignId('recibido_procura_por_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->string('tipo_documento_recepcion', 40)->nullable();
            $table->string('estado_recepcion_procura', 40)->default('RECIBIDO')->index();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('pbi_entregas_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('pbi_ordenes_compra')->cascadeOnDelete();
            $table->date('fecha_entrega_almacen')->index();
            $table->foreignId('recibido_almacen_por_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->string('estado_entrega_almacen', 40)->default('EN_TRANSICION')->index();
            $table->decimal('porcentaje_cumplimiento', 5, 2)->default(100);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('pbi_revision_solicitante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('pbi_ordenes_compra')->cascadeOnDelete();
            $table->date('fecha_revision')->index();
            $table->foreignId('solicitante_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->string('decision', 20)->default('PENDIENTE')->index();
            $table->text('motivo_rechazo')->nullable();
            $table->date('fecha_devolucion_proveedor')->nullable();
            $table->timestamps();
        });

        Schema::create('pbi_documentacion_administracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('pbi_ordenes_compra')->cascadeOnDelete();
            $table->date('fecha_entrega_administracion')->nullable()->index();
            $table->foreignId('entregado_por_procura_user_id')->nullable()->constrained('pbi_usuarios')->nullOnDelete();
            $table->string('estado_documentacion', 40)->default('PENDIENTE_ENTREGA')->index();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('pbi_facturas_finanzas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('pbi_ordenes_compra')->restrictOnDelete();
            $table->foreignId('documentacion_administracion_id')->nullable()->constrained('pbi_documentacion_administracion')->nullOnDelete();
            $table->string('numero_factura', 60);
            $table->date('fecha_factura')->index();
            $table->date('fecha_recepcion_finanzas')->nullable()->index();
            $table->date('fecha_carga_factura')->nullable()->index();
            $table->decimal('monto_base', 14, 2)->default(0);
            $table->decimal('monto_impuestos', 14, 2)->default(0);
            $table->decimal('monto_total', 14, 2)->default(0);
            $table->string('estado_finanzas', 40)->default('RECIBIDA')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbi_facturas_finanzas');
        Schema::dropIfExists('pbi_documentacion_administracion');
        Schema::dropIfExists('pbi_revision_solicitante');
        Schema::dropIfExists('pbi_entregas_almacen');
        Schema::dropIfExists('pbi_recepciones_procura');
        Schema::dropIfExists('pbi_pagos_finanzas');
        Schema::dropIfExists('pbi_orden_compra_items');
        Schema::dropIfExists('pbi_ordenes_compra');
        Schema::dropIfExists('pbi_sumario_items');
        Schema::dropIfExists('pbi_sumarios_cotizacion');
        Schema::dropIfExists('pbi_solicitud_items');
        Schema::dropIfExists('pbi_solicitudes_compra');
        Schema::dropIfExists('pbi_productos');
        Schema::dropIfExists('pbi_categorias_producto');
        Schema::dropIfExists('pbi_proveedores');
        Schema::dropIfExists('pbi_usuarios');
        Schema::dropIfExists('pbi_departamentos');
        Schema::dropIfExists('pbi_roles');
    }
};
