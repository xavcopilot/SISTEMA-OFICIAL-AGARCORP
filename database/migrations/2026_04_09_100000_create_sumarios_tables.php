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
        Schema::create('sumarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_compra_id')->constrained('solicitud_compras')->cascadeOnDelete();

            $table->string('correlativo_sdc')->unique();
            $table->date('fecha');
            $table->enum('procedencia', ['LOCAL', 'IMPORTADO']);
            $table->enum('tipo_orden', ['COMPRA', 'SERVICIO']);
            $table->string('departamento_solicitante');

            $table->decimal('total_compra_prov1', 14, 2)->default(0);
            $table->decimal('total_compra_prov2', 14, 2)->default(0);
            $table->decimal('total_compra_prov3', 14, 2)->default(0);
            $table->string('condiciones_pago')->nullable();
            $table->string('tiempo_entrega')->nullable();

            $table->enum('prioridad', ['MEJOR_PRECIO', 'CALIDAD'])->nullable();
            $table->foreignId('proveedor_ganador_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->text('observaciones')->nullable();

            $table->foreignId('elaborado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisado_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('sumario_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sumario_id')->constrained('sumarios')->cascadeOnDelete();
            $table->foreignId('solicitud_compra_item_id')->constrained('solicitud_compra_items')->cascadeOnDelete();

            $table->unsignedInteger('item')->nullable();
            $table->string('descripcion');
            $table->string('unidad_medida', 20)->default('UND');
            $table->decimal('cantidad', 12, 2);

            $table->timestamps();

            $table->unique(['sumario_id', 'solicitud_compra_item_id'], 'sumario_item_unique');
        });

        Schema::create('sumario_item_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sumario_item_id')->constrained('sumario_items')->cascadeOnDelete();

            // Posicion 1, 2 o 3 en el cuadro comparativo del formato SDC.
            $table->unsignedTinyInteger('opcion_numero');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('proveedor_nombre');
            $table->string('marca')->nullable();
            $table->decimal('precio_unitario', 14, 2)->default(0);
            $table->decimal('precio_total', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['sumario_item_id', 'opcion_numero'], 'sumario_opcion_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sumario_item_opciones');
        Schema::dropIfExists('sumario_items');
        Schema::dropIfExists('sumarios');
    }
};
