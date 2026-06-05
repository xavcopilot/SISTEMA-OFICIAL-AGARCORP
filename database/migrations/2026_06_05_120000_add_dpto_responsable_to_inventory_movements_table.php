<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('dpto_responsable')->nullable()->after('dpto_destino');
        });

        DB::statement(
            "UPDATE inventory_movements
            SET dpto_responsable = COALESCE(NULLIF(dpto_destino, ''), NULLIF(responsable_destino, ''))
            WHERE dpto_responsable IS NULL OR dpto_responsable = ''"
        );
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('dpto_responsable');
        });
    }
};