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
            $table->text('descripcion')->nullable()->change();
        });

        Schema::table('sumario_items', function (Blueprint $table) {
            $table->text('descripcion')->change();
        });

        Schema::table('orden_compra_items', function (Blueprint $table) {
            $table->text('descripcion')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_compra_items', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable()->change();
        });

        Schema::table('sumario_items', function (Blueprint $table) {
            $table->string('descripcion', 255)->change();
        });

        Schema::table('orden_compra_items', function (Blueprint $table) {
            $table->string('descripcion', 255)->change();
        });
    }
};