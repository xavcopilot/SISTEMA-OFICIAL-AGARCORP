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
        Schema::table('sumarios', function (Blueprint $table) {
            $table->enum('estado', [
                'BORRADOR',
                'PENDIENTE_REVISION_FINANZAS',
                'REVISADO_FINANZAS',
            ])->default('BORRADOR')->after('revisado_por_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sumarios', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
