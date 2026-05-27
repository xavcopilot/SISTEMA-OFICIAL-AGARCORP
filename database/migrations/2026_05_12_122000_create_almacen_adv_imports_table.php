<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacen_adv_imports', function (Blueprint $table) {
            $table->id();
            $table->string('SKU')->nullable()->index();
            $table->text('PRODUCTO')->nullable();
            $table->string('MARCA')->nullable();
            $table->string('CATEGORIA')->nullable()->index();
            $table->string('SUBCATG')->nullable()->index();
            $table->string('ESTADO')->nullable();
            $table->string('MEDIDA')->nullable();
            $table->string('SERIAL')->nullable()->index();
            $table->string('ALMACEN')->nullable();
            $table->string('UBICACION')->nullable();
            $table->string('RESPONSABLE')->nullable();
            $table->string('MIN')->nullable();
            $table->string('STATUS (1,2,3)')->nullable();
            $table->string('CANT_TOTAL')->nullable();
            $table->string('ENTRADAS')->nullable();
            $table->string('SALIDAS')->nullable();
            $table->string('P_UNITARIO')->nullable();
            $table->string('P_TOTAL')->nullable();
            $table->string('FECHA DE ADQUISICION')->nullable();
            $table->string('FECHA DE ULTIMA ENTRADA')->nullable();
            $table->string('FECHA DE ULTIMA SALIDA')->nullable();
            $table->string('ESTADO REGISTRO')->nullable();
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
        Schema::dropIfExists('almacen_adv_imports');
    }
};