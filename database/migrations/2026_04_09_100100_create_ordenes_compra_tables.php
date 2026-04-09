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
            $table->decimal('tasa_bcv', 14, 6)->nullable();
            $table->string('condicion_pago')->nullable();

            $table->decimal('monto_exento', 14, 2)->default(0);
            $table->decimal('sub_total', 14, 2)->default(0);
            $table->decimal('iva_16', 14, 2)->default(0);
            $table->decimal('gastos_adicionales', 14, 2)->default(0);
            $table->decimal('total_general', 14, 2)->default(0);

            $table->enum('estado', [
                'PENDIENTE_APROBACION',
                'PAGADA',
                'EN_ESPERA_DE_PRODUCTO',
                'RECIBIDA',
            ])->default('PENDIENTE_APROBACION');

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
