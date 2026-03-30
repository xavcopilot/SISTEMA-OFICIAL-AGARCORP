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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('cod_ingreso');
            $table->text('descripcion');
            $table->string('marca');
            $table->foreignId('subcategory_id')->constrained('subcategories')->cascadeOnDelete();
            $table->string('serial');
            $table->string('estado');
            $table->string('medida');
            $table->string('ubicacion');
            $table->string('dpto_responsable');
            $table->unsignedInteger('stock_minimo');
            $table->unsignedInteger('stock_actual')->default(0);
            $table->decimal('precio_unitario', 14, 2);
            $table->date('fecha_adquisicion');
            $table->date('fecha_ultima_entrada')->nullable();
            $table->date('fecha_ultima_salida')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
