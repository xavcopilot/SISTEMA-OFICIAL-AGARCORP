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
        Schema::create('solicitud_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_compra_id')->constrained('solicitud_compras')->cascadeOnDelete();
            $table->unsignedInteger('item')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('unidad_medida', 20)->nullable()->default('UND');
            $table->decimal('cantidad_solicitada', 12, 2)->nullable();
            $table->decimal('cantidad_existencia', 12, 2)->nullable();
            $table->decimal('cantidad_a_comprar', 12, 2)->nullable();
            $table->string('estado_item', 40)->default('SIN_PROCESAR');
            $table->decimal('cantidad_pedida', 12, 2)->default(0);
            $table->decimal('cantidad_en_sumario', 12, 2)->default(0);
            $table->decimal('cantidad_comprada', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_compra_items');
    }
};
