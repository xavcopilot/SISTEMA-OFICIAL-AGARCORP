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
        Schema::table('solicitud_compra_items', function (Blueprint $table) {
            $table->string('descripcion')->nullable()->change();
            $table->string('unidad_medida', 20)->nullable()->change();
            $table->decimal('cantidad_solicitada', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_compra_items', function (Blueprint $table) {
            $table->string('descripcion')->nullable(false)->change();
            $table->string('unidad_medida', 20)->nullable(false)->default('UND')->change();
            $table->decimal('cantidad_solicitada', 12, 2)->nullable(false)->change();
        });
    }
};
