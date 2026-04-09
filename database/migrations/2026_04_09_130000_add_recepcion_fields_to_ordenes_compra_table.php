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
            $table->enum('tipo_documento_recepcion', ['FACTURA', 'NOTA'])->nullable()->after('estado');
            $table->string('factura_path')->nullable()->after('tipo_documento_recepcion');
            $table->boolean('factura_pendiente')->default(false)->after('factura_path');
            $table->timestamp('recepcion_procesada_at')->nullable()->after('factura_pendiente');
            $table->foreignId('recibido_por_user_id')->nullable()->after('recepcion_procesada_at')->constrained('users')->nullOnDelete();
            $table->timestamp('conformidad_solicitante_at')->nullable()->after('recibido_por_user_id');
            $table->foreignId('conformidad_por_user_id')->nullable()->after('conformidad_solicitante_at')->constrained('users')->nullOnDelete();
            $table->foreignId('inventario_movimiento_id')->nullable()->after('conformidad_por_user_id')->constrained('inventory_movements')->nullOnDelete();
            $table->timestamp('factura_procesada_administracion_at')->nullable()->after('inventario_movimiento_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recibido_por_user_id');
            $table->dropConstrainedForeignId('conformidad_por_user_id');
            $table->dropConstrainedForeignId('inventario_movimiento_id');
            $table->dropColumn([
                'tipo_documento_recepcion',
                'factura_path',
                'factura_pendiente',
                'recepcion_procesada_at',
                'conformidad_solicitante_at',
                'factura_procesada_administracion_at',
            ]);
        });
    }
};
