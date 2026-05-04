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
        // Postgres: Drop the check constraint created by Laravel ENUMs
        DB::statement('ALTER TABLE solicitud_compras DROP CONSTRAINT IF EXISTS solicitud_compras_estado_check');
        DB::statement('ALTER TABLE solicitud_compras DROP CONSTRAINT IF EXISTS solicitud_compras_tipo_solicitud_check');
        DB::statement('ALTER TABLE sumarios DROP CONSTRAINT IF EXISTS sumarios_estado_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-enabling the check constraints if needed
    }
};
