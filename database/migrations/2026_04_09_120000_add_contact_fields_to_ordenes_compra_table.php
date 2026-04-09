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
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('rif_proveedor')->nullable()->after('proveedor_id');
            $table->string('direccion_proveedor')->nullable()->after('rif_proveedor');
            $table->string('email_proveedor')->nullable()->after('direccion_proveedor');
            $table->string('contacto_proveedor')->nullable()->after('email_proveedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn([
                'rif_proveedor',
                'direccion_proveedor',
                'email_proveedor',
                'contacto_proveedor',
            ]);
        });
    }
};
