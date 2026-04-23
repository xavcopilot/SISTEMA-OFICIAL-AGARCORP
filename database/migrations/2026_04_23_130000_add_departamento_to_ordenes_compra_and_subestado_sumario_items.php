<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_compra', 'departamento_solicitante')) {
                $table->string('departamento_solicitante')
                    ->nullable()
                    ->after('condicion_pago');
            }
        });

        Schema::table('sumario_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sumario_items', 'sub_estado')) {
                $table->string('sub_estado', 60)
                    ->nullable()
                    ->after('validacion_gerencia_comentario');
            }
        });

        DB::table('ordenes_compra as oc')
            ->join('sumarios as s', 's.id', '=', 'oc.sumario_id')
            ->whereNull('oc.departamento_solicitante')
            ->update([
                'departamento_solicitante' => DB::raw('s.departamento_solicitante'),
            ]);

        DB::table('sumario_items')
            ->whereNull('sub_estado')
            ->update(['sub_estado' => 'PENDIENTE_OC']);

        DB::table('sumario_items as si')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('orden_compra_items as oci')
                    ->whereColumn('oci.sumario_item_id', 'si.id');
            })
            ->update(['sub_estado' => 'EN_PROCESO_DE_PAGO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sumario_items', function (Blueprint $table) {
            if (Schema::hasColumn('sumario_items', 'sub_estado')) {
                $table->dropColumn('sub_estado');
            }
        });

        Schema::table('ordenes_compra', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_compra', 'departamento_solicitante')) {
                $table->dropColumn('departamento_solicitante');
            }
        });
    }
};
