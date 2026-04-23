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
        Schema::table('orden_compra_items', function (Blueprint $table) {
            if (! Schema::hasColumn('orden_compra_items', 'decision_solicitante')) {
                $table->string('decision_solicitante', 20)->nullable()->after('entregado_at');
            }

            if (! Schema::hasColumn('orden_compra_items', 'motivo_rechazo_solicitante')) {
                $table->text('motivo_rechazo_solicitante')->nullable()->after('decision_solicitante');
            }

            if (! Schema::hasColumn('orden_compra_items', 'conformidad_solicitante_at')) {
                $table->timestamp('conformidad_solicitante_at')->nullable()->after('motivo_rechazo_solicitante');
            }

            if (! Schema::hasColumn('orden_compra_items', 'procesado_almacen_at')) {
                $table->timestamp('procesado_almacen_at')->nullable()->after('conformidad_solicitante_at');
            }

            if (! Schema::hasColumn('orden_compra_items', 'modo_ingreso_almacen')) {
                $table->string('modo_ingreso_almacen', 30)->nullable()->after('procesado_almacen_at');
            }

            if (! Schema::hasColumn('orden_compra_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('modo_ingreso_almacen')->constrained('products')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_compra_items', function (Blueprint $table) {
            if (Schema::hasColumn('orden_compra_items', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }

            foreach ([
                'modo_ingreso_almacen',
                'procesado_almacen_at',
                'conformidad_solicitante_at',
                'motivo_rechazo_solicitante',
                'decision_solicitante',
            ] as $column) {
                if (Schema::hasColumn('orden_compra_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
