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
        Schema::table('sumario_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sumario_items', 'validacion_gerencia_resultado')) {
                $table->string('validacion_gerencia_resultado', 20)
                    ->nullable()
                    ->after('cantidad');
            }

            if (! Schema::hasColumn('sumario_items', 'validacion_gerencia_comentario')) {
                $table->text('validacion_gerencia_comentario')
                    ->nullable()
                    ->after('validacion_gerencia_resultado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sumario_items', function (Blueprint $table) {
            if (Schema::hasColumn('sumario_items', 'validacion_gerencia_comentario')) {
                $table->dropColumn('validacion_gerencia_comentario');
            }

            if (Schema::hasColumn('sumario_items', 'validacion_gerencia_resultado')) {
                $table->dropColumn('validacion_gerencia_resultado');
            }
        });
    }
};
