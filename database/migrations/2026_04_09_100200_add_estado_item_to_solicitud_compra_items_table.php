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
            $table->enum('estado_item', ['SIN_PROCESAR', 'EN_SUMARIO', 'EN_OC'])
                ->default('SIN_PROCESAR')
                ->after('cantidad_a_comprar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_compra_items', function (Blueprint $table) {
            $table->dropColumn('estado_item');
        });
    }
};
