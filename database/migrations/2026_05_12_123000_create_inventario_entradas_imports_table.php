<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_entradas_imports', function (Blueprint $table) {
            $table->id();
            $table->string('N° CONTROL')->nullable()->index();
            $table->string('FECHA')->nullable()->index();
            $table->string('MES', 30)->nullable();
            $table->string('N° DE SOLICITUD')->nullable();
            $table->string('ORDEN DE COMPRA')->nullable();
            $table->string('F/N/I')->nullable();
            $table->string('N°')->nullable();
            $table->string('PROVEEDOR')->nullable()->index();
            $table->string('SKU')->nullable()->index();
            $table->text('DESCRIPCION')->nullable();
            $table->string('MARCA')->nullable();
            $table->string('CATEGORIA')->nullable()->index();
            $table->string('SUBCAT')->nullable()->index();
            $table->string('SERIAL')->nullable()->index();
            $table->string('ESTADO')->nullable();
            $table->string('MEDIDA')->nullable();
            $table->string('CANT')->nullable();
            $table->string('UBICACION')->nullable();
            $table->string('DPTO RESPONSIBLE')->nullable();
            $table->string('PRECIO')->nullable();
            $table->text('COMENTARIO')->nullable();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->foreignId('movement_item_id')->nullable()->constrained('movement_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('lote_importacion')->nullable()->index();
            $table->boolean('procesado')->default(false)->index();
            $table->timestamp('procesado_en')->nullable();
            $table->text('error_importacion')->nullable();
            $table->json('datos_originales')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_entradas_imports');
    }
};