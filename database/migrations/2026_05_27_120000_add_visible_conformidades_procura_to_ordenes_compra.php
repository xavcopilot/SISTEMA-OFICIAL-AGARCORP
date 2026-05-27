<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->boolean('visible_conformidades_procura')
                ->default(false)
                ->after('conformidad_por_user_id');
        });

        DB::table('ordenes_compra')
            ->where('workflow_post_compra', 'DEVOLUCION_REALIZADA')
            ->update(['visible_conformidades_procura' => true]);
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn('visible_conformidades_procura');
        });
    }
};