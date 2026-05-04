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
        $driver = DB::getDriverName();

        DB::statement('DROP VIEW IF EXISTS vw_solicitud_trazabilidad');

        if ($driver === 'pgsql') {
            DB::statement(
                <<<'SQL'
                CREATE VIEW vw_solicitud_trazabilidad AS
                SELECT
                    sc.id AS solicitud_compra_id,
                    sci.id AS solicitud_compra_item_id,
                    COALESCE(sc.numero_solicitud_usuario::text, sc.id::text) AS solicitud_numero,
                    COALESCE(string_agg(DISTINCT s.id::text, ','), '') AS sumario_ids,
                    COALESCE(string_agg(DISTINCT oc.id::text, ','), '') AS orden_compra_ids,
                    COALESCE(sci.cantidad_pedida, 0) AS cantidad_pedida,
                    COALESCE(sci.cantidad_en_sumario, 0) AS cantidad_en_sumario,
                    COALESCE(sci.cantidad_comprada, 0) AS cantidad_comprada,
                    GREATEST(COALESCE(sci.cantidad_pedida, 0) - COALESCE(sci.cantidad_comprada, 0), 0) AS cantidad_faltante,
                    CASE
                        WHEN COALESCE(sci.cantidad_comprada, 0) >= COALESCE(sci.cantidad_pedida, 0) AND COALESCE(sci.cantidad_pedida, 0) > 0 THEN 'Comprado'
                        WHEN COALESCE(sci.cantidad_comprada, 0) > 0 THEN 'Faltante'
                        ELSE 'Pedido'
                    END AS estado_item_trazabilidad
                FROM solicitud_compras sc
                INNER JOIN solicitud_compra_items sci ON sci.solicitud_compra_id = sc.id
                LEFT JOIN sumario_items si ON si.solicitud_compra_item_id = sci.id
                LEFT JOIN sumarios s ON s.id = si.sumario_id
                LEFT JOIN orden_compra_items oci ON oci.solicitud_compra_item_id = sci.id
                LEFT JOIN ordenes_compra oc ON oc.id = oci.orden_compra_id
                GROUP BY
                    sc.id,
                    sci.id,
                    sc.numero_solicitud_usuario,
                    sci.cantidad_pedida,
                    sci.cantidad_en_sumario,
                    sci.cantidad_comprada
                SQL
            );

            return;
        }

        DB::statement(
            <<<'SQL'
            CREATE VIEW vw_solicitud_trazabilidad AS
            SELECT
                sc.id AS solicitud_compra_id,
                sci.id AS solicitud_compra_item_id,
                COALESCE(CAST(sc.numero_solicitud_usuario AS CHAR), CAST(sc.id AS CHAR)) AS solicitud_numero,
                COALESCE(GROUP_CONCAT(DISTINCT s.id ORDER BY s.id SEPARATOR ','), '') AS sumario_ids,
                COALESCE(GROUP_CONCAT(DISTINCT oc.id ORDER BY oc.id SEPARATOR ','), '') AS orden_compra_ids,
                COALESCE(sci.cantidad_pedida, 0) AS cantidad_pedida,
                COALESCE(sci.cantidad_en_sumario, 0) AS cantidad_en_sumario,
                COALESCE(sci.cantidad_comprada, 0) AS cantidad_comprada,
                GREATEST(COALESCE(sci.cantidad_pedida, 0) - COALESCE(sci.cantidad_comprada, 0), 0) AS cantidad_faltante,
                CASE
                    WHEN COALESCE(sci.cantidad_comprada, 0) >= COALESCE(sci.cantidad_pedida, 0) AND COALESCE(sci.cantidad_pedida, 0) > 0 THEN 'Comprado'
                    WHEN COALESCE(sci.cantidad_comprada, 0) > 0 THEN 'Faltante'
                    ELSE 'Pedido'
                END AS estado_item_trazabilidad
            FROM solicitud_compras sc
            INNER JOIN solicitud_compra_items sci ON sci.solicitud_compra_id = sc.id
            LEFT JOIN sumario_items si ON si.solicitud_compra_item_id = sci.id
            LEFT JOIN sumarios s ON s.id = si.sumario_id
            LEFT JOIN orden_compra_items oci ON oci.solicitud_compra_item_id = sci.id
            LEFT JOIN ordenes_compra oc ON oc.id = oci.orden_compra_id
            GROUP BY
                sc.id,
                sci.id,
                sc.numero_solicitud_usuario,
                sci.cantidad_pedida,
                sci.cantidad_en_sumario,
                sci.cantidad_comprada
            SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_solicitud_trazabilidad');
    }
};
