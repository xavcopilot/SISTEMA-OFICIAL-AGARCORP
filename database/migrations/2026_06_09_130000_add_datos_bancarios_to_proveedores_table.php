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
        Schema::table('proveedores', function (Blueprint $table): void {
            $table->string('banco')->nullable()->after('telefono');
            $table->string('numero_cuenta', 50)->nullable()->after('banco');
            $table->string('tipo_documento', 1)->nullable()->after('numero_cuenta');
            $table->string('documento', 50)->nullable()->after('tipo_documento');
            $table->string('beneficiario_nombre_apellido')->nullable()->after('documento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table): void {
            $table->dropColumn([
                'banco',
                'numero_cuenta',
                'tipo_documento',
                'documento',
                'beneficiario_nombre_apellido',
            ]);
        });
    }
};
