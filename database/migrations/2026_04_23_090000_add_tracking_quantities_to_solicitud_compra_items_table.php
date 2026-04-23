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
        Schema::table('solicitud_compra_items', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitud_compra_items', 'cantidad_pedida')) {
                $table->decimal('cantidad_pedida', 12, 2)->default(0)->after('cantidad_a_comprar');
            }

            if (! Schema::hasColumn('solicitud_compra_items', 'cantidad_en_sumario')) {
                $table->decimal('cantidad_en_sumario', 12, 2)->default(0)->after('cantidad_pedida');
            }

            if (! Schema::hasColumn('solicitud_compra_items', 'cantidad_comprada')) {
                $table->decimal('cantidad_comprada', 12, 2)->default(0)->after('cantidad_en_sumario');
            }
        });

        DB::table('solicitud_compra_items')
            ->select(['id', 'cantidad_solicitada', 'cantidad_a_comprar'])
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    $cantidadPedida = round((float) ($item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);

                    $cantidadEnSumario = round((float) DB::table('sumario_items')
                        ->where('solicitud_compra_item_id', $item->id)
                        ->sum('cantidad'), 2);

                    $cantidadComprada = round((float) DB::table('orden_compra_items')
                        ->where('solicitud_compra_item_id', $item->id)
                        ->sum('cantidad'), 2);

                    DB::table('solicitud_compra_items')
                        ->where('id', $item->id)
                        ->update([
                            'cantidad_pedida' => $cantidadPedida,
                            'cantidad_en_sumario' => $cantidadEnSumario,
                            'cantidad_comprada' => $cantidadComprada,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_compra_items', function (Blueprint $table) {
            if (Schema::hasColumn('solicitud_compra_items', 'cantidad_comprada')) {
                $table->dropColumn('cantidad_comprada');
            }

            if (Schema::hasColumn('solicitud_compra_items', 'cantidad_en_sumario')) {
                $table->dropColumn('cantidad_en_sumario');
            }

            if (Schema::hasColumn('solicitud_compra_items', 'cantidad_pedida')) {
                $table->dropColumn('cantidad_pedida');
            }
        });
    }
};
