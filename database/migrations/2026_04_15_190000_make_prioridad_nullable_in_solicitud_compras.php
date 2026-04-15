<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE solicitud_compras ALTER COLUMN prioridad DROP NOT NULL');
        DB::statement('ALTER TABLE solicitud_compras ALTER COLUMN prioridad DROP DEFAULT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE solicitud_compras SET prioridad = 'Media' WHERE prioridad IS NULL");
        DB::statement("ALTER TABLE solicitud_compras ALTER COLUMN prioridad SET DEFAULT 'Media'");
        DB::statement('ALTER TABLE solicitud_compras ALTER COLUMN prioridad SET NOT NULL');
    }
};
