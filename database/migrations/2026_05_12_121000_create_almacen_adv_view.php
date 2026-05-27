<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS "Almacen_ADV"');
            DB::statement(
                <<<'SQL'
                CREATE VIEW "Almacen_ADV" AS
                SELECT
                    p.sku AS "SKU",
                    p.descripcion AS "Producto",
                    p.marca AS "Marca",
                    c.name AS "Categoria",
                    s.name AS "Subcatg",
                    p.estado AS "Estado",
                    p.medida AS "Medida",
                    p.serial AS "Serial",
                    'ALMACEN' AS "Almacen",
                    p.ubicacion AS "Ubicacion",
                    p.dpto_responsable AS "Dpto Responsable",
                    p.stock_minimo AS "Min",
                    CASE
                        WHEN p.stock_actual < p.stock_minimo THEN 3
                        WHEN p.stock_actual = p.stock_minimo THEN 2
                        ELSE 1
                    END AS "Status",
                    p.stock_actual AS "Cant. Total",
                    COALESCE(mv.entradas, 0) AS "Entradas",
                    COALESCE(mv.salidas, 0) AS "Salidas",
                    p.precio_unitario AS "P.Unitario",
                    p.precio_total AS "P.Total",
                    p.fecha_adquisicion AS "Fecha de Adquisicion",
                    p.fecha_ultima_entrada AS "Fecha de Ultima Entrada",
                    p.fecha_ultima_salida AS "Fecha de Ultima Salida",
                    CASE
                        WHEN p.is_archived THEN 'Archivado'
                        ELSE 'Activo'
                    END AS "Estado Registro"
                FROM products p
                LEFT JOIN subcategories s ON s.id = p.subcategory_id
                LEFT JOIN categories c ON c.id = s.category_id
                LEFT JOIN (
                    SELECT
                        mi.product_id,
                        SUM(CASE WHEN im.tipo IN ('ingreso', 'entrada') THEN mi.cantidad ELSE 0 END) AS entradas,
                        SUM(CASE WHEN im.tipo = 'salida' THEN mi.cantidad ELSE 0 END) AS salidas
                    FROM movement_items mi
                    INNER JOIN inventory_movements im ON im.id = mi.movement_id
                    GROUP BY mi.product_id
                ) mv ON mv.product_id = p.id
                SQL
            );

            return;
        }

        DB::statement('DROP VIEW IF EXISTS Almacen_ADV');
        DB::statement(
            <<<'SQL'
            CREATE VIEW Almacen_ADV AS
            SELECT
                p.sku AS `SKU`,
                p.descripcion AS `Producto`,
                p.marca AS `Marca`,
                c.name AS `Categoria`,
                s.name AS `Subcatg`,
                p.estado AS `Estado`,
                p.medida AS `Medida`,
                p.serial AS `Serial`,
                'ALMACEN' AS `Almacen`,
                p.ubicacion AS `Ubicacion`,
                p.dpto_responsable AS `Dpto Responsable`,
                p.stock_minimo AS `Min`,
                CASE
                    WHEN p.stock_actual < p.stock_minimo THEN 3
                    WHEN p.stock_actual = p.stock_minimo THEN 2
                    ELSE 1
                END AS `Status`,
                p.stock_actual AS `Cant. Total`,
                COALESCE(mv.entradas, 0) AS `Entradas`,
                COALESCE(mv.salidas, 0) AS `Salidas`,
                p.precio_unitario AS `P.Unitario`,
                p.precio_total AS `P.Total`,
                p.fecha_adquisicion AS `Fecha de Adquisicion`,
                p.fecha_ultima_entrada AS `Fecha de Ultima Entrada`,
                p.fecha_ultima_salida AS `Fecha de Ultima Salida`,
                CASE
                    WHEN p.is_archived THEN 'Archivado'
                    ELSE 'Activo'
                END AS `Estado Registro`
            FROM products p
            LEFT JOIN subcategories s ON s.id = p.subcategory_id
            LEFT JOIN categories c ON c.id = s.category_id
            LEFT JOIN (
                SELECT
                    mi.product_id,
                    SUM(CASE WHEN im.tipo IN ('ingreso', 'entrada') THEN mi.cantidad ELSE 0 END) AS entradas,
                    SUM(CASE WHEN im.tipo = 'salida' THEN mi.cantidad ELSE 0 END) AS salidas
                FROM movement_items mi
                INNER JOIN inventory_movements im ON im.id = mi.movement_id
                GROUP BY mi.product_id
            ) mv ON mv.product_id = p.id
            SQL
        );
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS "Almacen_ADV"');

            return;
        }

        DB::statement('DROP VIEW IF EXISTS Almacen_ADV');
    }
};