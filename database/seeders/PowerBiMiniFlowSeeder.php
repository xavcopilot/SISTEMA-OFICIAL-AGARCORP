<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PowerBiMiniFlowSeeder extends Seeder
{
    /**
     * Seed de ejemplo para exposicion Power BI.
     */
    public function run(): void
    {
        if (! Schema::hasTable('pbi_roles')) {
            $this->command?->warn('No existen tablas pbi_*. Ejecuta primero las migraciones.');

            return;
        }

        DB::transaction(function (): void {
            Schema::disableForeignKeyConstraints();

            DB::table('pbi_facturas_finanzas')->delete();
            DB::table('pbi_documentacion_administracion')->delete();
            DB::table('pbi_revision_solicitante')->delete();
            DB::table('pbi_entregas_almacen')->delete();
            DB::table('pbi_recepciones_procura')->delete();
            DB::table('pbi_pagos_finanzas')->delete();
            DB::table('pbi_orden_compra_items')->delete();
            DB::table('pbi_ordenes_compra')->delete();
            DB::table('pbi_sumario_items')->delete();
            DB::table('pbi_sumarios_cotizacion')->delete();
            DB::table('pbi_solicitud_items')->delete();
            DB::table('pbi_solicitudes_compra')->delete();
            DB::table('pbi_productos')->delete();
            DB::table('pbi_categorias_producto')->delete();
            DB::table('pbi_proveedores')->delete();
            DB::table('pbi_usuarios')->delete();
            DB::table('pbi_departamentos')->delete();
            DB::table('pbi_roles')->delete();

            Schema::enableForeignKeyConstraints();

            $now = now();

            DB::table('pbi_roles')->insert([
                ['nombre' => 'OPERACIONES', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'TALENTO HUMANO', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'S.I.H.O', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'MANTENIMIENTO', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'CALIDAD', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'PROCURA', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'ALMACEN', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'FINANZAS', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'ADMINISTRACION', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'GERENCIA', 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::table('pbi_departamentos')->insert([
                ['nombre' => 'OPERACIONES', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'PROCURA', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'ALMACEN', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'FINANZAS', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'ADMINISTRACION', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'GERENCIA', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'TALENTO HUMANO', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'S.I.H.O', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'MANTENIMIENTO', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'CALIDAD', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $rolId = fn (string $nombre): int => (int) DB::table('pbi_roles')->where('nombre', $nombre)->value('id');
            $deptoId = fn (string $nombre): int => (int) DB::table('pbi_departamentos')->where('nombre', $nombre)->value('id');

            DB::table('pbi_usuarios')->insert([
                [
                    'nombre' => 'Ana Operaciones',
                    'email' => 'ana.solicitante@demo.local',
                    'rol_id' => $rolId('OPERACIONES'),
                    'departamento_id' => $deptoId('OPERACIONES'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Luis Talento Humano',
                    'email' => 'luis.th@demo.local',
                    'rol_id' => $rolId('TALENTO HUMANO'),
                    'departamento_id' => $deptoId('TALENTO HUMANO'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Marta S.I.H.O',
                    'email' => 'marta.siho@demo.local',
                    'rol_id' => $rolId('S.I.H.O'),
                    'departamento_id' => $deptoId('S.I.H.O'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Jose Mantenimiento',
                    'email' => 'jose.mant@demo.local',
                    'rol_id' => $rolId('MANTENIMIENTO'),
                    'departamento_id' => $deptoId('MANTENIMIENTO'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Sofia Calidad',
                    'email' => 'sofia.calidad@demo.local',
                    'rol_id' => $rolId('CALIDAD'),
                    'departamento_id' => $deptoId('CALIDAD'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Pedro Procura',
                    'email' => 'pedro.procura@demo.local',
                    'rol_id' => $rolId('PROCURA'),
                    'departamento_id' => $deptoId('PROCURA'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Laura Procura',
                    'email' => 'laura.procura@demo.local',
                    'rol_id' => $rolId('PROCURA'),
                    'departamento_id' => $deptoId('PROCURA'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Diego Procura',
                    'email' => 'diego.procura@demo.local',
                    'rol_id' => $rolId('PROCURA'),
                    'departamento_id' => $deptoId('PROCURA'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Luisa Almacen',
                    'email' => 'luisa.almacen@demo.local',
                    'rol_id' => $rolId('ALMACEN'),
                    'departamento_id' => $deptoId('ALMACEN'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Carlos Finanzas',
                    'email' => 'carlos.finanzas@demo.local',
                    'rol_id' => $rolId('FINANZAS'),
                    'departamento_id' => $deptoId('FINANZAS'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Maria Administracion',
                    'email' => 'maria.admin@demo.local',
                    'rol_id' => $rolId('ADMINISTRACION'),
                    'departamento_id' => $deptoId('ADMINISTRACION'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'nombre' => 'Gerson Gerencia',
                    'email' => 'gerson.gerencia@demo.local',
                    'rol_id' => $rolId('GERENCIA'),
                    'departamento_id' => $deptoId('GERENCIA'),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $usuarioId = fn (string $email): int => (int) DB::table('pbi_usuarios')->where('email', $email)->value('id');

            DB::table('pbi_proveedores')->insert([
                ['rif' => 'J-40123456-1', 'nombre' => 'Tech Import C.A.', 'categoria' => 'TECNOLOGIA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-40999999-2', 'nombre' => 'Suministros Delta, C.A.', 'categoria' => 'OFICINA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-41234567-3', 'nombre' => 'Industrial Lara, C.A.', 'categoria' => 'INDUSTRIAL', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-42345678-4', 'nombre' => 'Global Office Supply C.A.', 'categoria' => 'OFICINA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-43456789-5', 'nombre' => 'Andes Safety & Health C.A.', 'categoria' => 'S.I.H.O', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-44567890-6', 'nombre' => 'Repuestos Industriales Orinoco C.A.', 'categoria' => 'INDUSTRIAL', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-45678901-7', 'nombre' => 'Servicios y Equipos TH C.A.', 'categoria' => 'TALENTO HUMANO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-46789012-8', 'nombre' => 'Calidad Integral Labs C.A.', 'categoria' => 'CALIDAD', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-47890123-9', 'nombre' => 'Mantenimiento Total C.A.', 'categoria' => 'MANTENIMIENTO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['rif' => 'J-48901234-0', 'nombre' => 'Distribuidora Centro Occidental C.A.', 'categoria' => 'MIXTA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::table('pbi_categorias_producto')->insert([
                ['nombre' => 'EQUIPOS', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'CONSUMIBLES', 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'REPUESTOS', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $categoriaId = fn (string $nombre): int => (int) DB::table('pbi_categorias_producto')->where('nombre', $nombre)->value('id');

            DB::table('pbi_productos')->insert([
                ['codigo' => 'PBI-LAP-15', 'nombre' => 'Laptop 15"', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 1200, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-IMP-LSR', 'nombre' => 'Impresora Laser', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 420, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-TON-85A', 'nombre' => 'Toner 85A', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'UND', 'costo_referencia' => 65, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-ROD-IND', 'nombre' => 'Rodamiento Industrial', 'categoria_id' => $categoriaId('REPUESTOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 140, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-TAB-OPS', 'nombre' => 'Tablet Operativa Rugerizada', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 580, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-RAD-COM', 'nombre' => 'Radio Portatil de Comunicacion', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 210, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-HER-KIT', 'nombre' => 'Kit de Herramientas Operativas', 'categoria_id' => $categoriaId('REPUESTOS'), 'unidad_medida' => 'KIT', 'costo_referencia' => 175, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-SIL-ERG', 'nombre' => 'Silla Ergonomica Ejecutiva', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 260, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-PROY-TH', 'nombre' => 'Proyector para Capacitacion', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 690, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-KIT-IND', 'nombre' => 'Kit de Induccion Corporativa', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'KIT', 'costo_referencia' => 38, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-CAS-SEG', 'nombre' => 'Casco de Seguridad', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'UND', 'costo_referencia' => 28, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-GAF-IND', 'nombre' => 'Gafas de Proteccion Industrial', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'UND', 'costo_referencia' => 14, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-BOT-SEG', 'nombre' => 'Botas de Seguridad', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'PAR', 'costo_referencia' => 46, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-CHA-REF', 'nombre' => 'Chaleco Reflectivo', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'UND', 'costo_referencia' => 19, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-LUB-IND', 'nombre' => 'Lubricante Industrial', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'UND', 'costo_referencia' => 32, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-COR-MTR', 'nombre' => 'Correa para Motor Industrial', 'categoria_id' => $categoriaId('REPUESTOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 84, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-SEN-TEMP', 'nombre' => 'Sensor de Temperatura', 'categoria_id' => $categoriaId('REPUESTOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 96, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-LLV-KIT', 'nombre' => 'Juego de Llaves Mixtas', 'categoria_id' => $categoriaId('REPUESTOS'), 'unidad_medida' => 'KIT', 'costo_referencia' => 58, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-PHM-MTR', 'nombre' => 'Medidor Portatil de PH', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 350, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-BAL-PRC', 'nombre' => 'Balanza de Precision', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 480, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-KIT-MUE', 'nombre' => 'Kit de Muestreo de Calidad', 'categoria_id' => $categoriaId('CONSUMIBLES'), 'unidad_medida' => 'KIT', 'costo_referencia' => 120, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
                ['codigo' => 'PBI-TERM-IR', 'nombre' => 'Termometro Infrarrojo', 'categoria_id' => $categoriaId('EQUIPOS'), 'unidad_medida' => 'UND', 'costo_referencia' => 89, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $productoId = fn (string $codigo): int => (int) DB::table('pbi_productos')->where('codigo', $codigo)->value('id');
            $proveedorId = fn (string $rif): int => (int) DB::table('pbi_proveedores')->where('rif', $rif)->value('id');
            $facturaNumero = fn (int $secuencia): string => 'F001-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT);

            DB::table('pbi_solicitudes_compra')->insert([
                [
                    'codigo' => 'SC-2026-0001',
                    'fecha_solicitud' => '2026-04-01',
                    'solicitante_user_id' => $usuarioId('ana.solicitante@demo.local'),
                    'departamento_solicitante_id' => $deptoId('OPERACIONES'),
                    'prioridad' => 'ALTA',
                    'estado' => 'COMPLETADA',
                    'monto_estimado_total' => 14040,
                    'fecha_requerida' => '2026-04-20',
                    'aprobada_almacen_at' => '2026-04-02 09:00:00',
                    'aprobada_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'codigo' => 'SC-2026-0002',
                    'fecha_solicitud' => '2026-04-05',
                    'solicitante_user_id' => $usuarioId('ana.solicitante@demo.local'),
                    'departamento_solicitante_id' => $deptoId('OPERACIONES'),
                    'prioridad' => 'MEDIA',
                    'estado' => 'COMPLETADA',
                    'monto_estimado_total' => 2800,
                    'fecha_requerida' => '2026-04-25',
                    'aprobada_almacen_at' => '2026-04-06 10:10:00',
                    'aprobada_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $solicitudId = fn (string $codigo): int => (int) DB::table('pbi_solicitudes_compra')->where('codigo', $codigo)->value('id');

            DB::table('pbi_solicitud_items')->insert([
                [
                    'solicitud_compra_id' => $solicitudId('SC-2026-0001'),
                    'producto_id' => $productoId('PBI-LAP-15'),
                    'descripcion' => 'Laptop 15" para equipo tecnico',
                    'cantidad' => 10,
                    'costo_estimado_unitario' => 1200,
                    'subtotal_estimado' => 12000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'solicitud_compra_id' => $solicitudId('SC-2026-0001'),
                    'producto_id' => $productoId('PBI-IMP-LSR'),
                    'descripcion' => 'Impresora laser para operaciones',
                    'cantidad' => 4,
                    'costo_estimado_unitario' => 420,
                    'subtotal_estimado' => 1680,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'solicitud_compra_id' => $solicitudId('SC-2026-0002'),
                    'producto_id' => $productoId('PBI-ROD-IND'),
                    'descripcion' => 'Rodamiento para linea de produccion',
                    'cantidad' => 20,
                    'costo_estimado_unitario' => 140,
                    'subtotal_estimado' => 2800,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $solicitudItemId = fn (int $solicitudId, int $productoId): int => (int) DB::table('pbi_solicitud_items')
                ->where('solicitud_compra_id', $solicitudId)
                ->where('producto_id', $productoId)
                ->value('id');

            DB::table('pbi_sumarios_cotizacion')->insert([
                [
                    'codigo' => 'SUM-2026-0001',
                    'solicitud_compra_id' => $solicitudId('SC-2026-0001'),
                    'analista_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'fecha_sumario' => '2026-04-03',
                    'estado' => 'CERRADO_PARCIAL',
                    'monto_referencial_total' => 8600,
                    'observaciones' => 'Primera compra parcial por disponibilidad de caja.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'codigo' => 'SUM-2026-0002',
                    'solicitud_compra_id' => $solicitudId('SC-2026-0001'),
                    'analista_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'fecha_sumario' => '2026-04-15',
                    'estado' => 'CERRADO',
                    'monto_referencial_total' => 5400,
                    'observaciones' => 'Cierre de cantidades pendientes de la solicitud.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'codigo' => 'SUM-2026-0003',
                    'solicitud_compra_id' => $solicitudId('SC-2026-0002'),
                    'analista_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'fecha_sumario' => '2026-04-07',
                    'estado' => 'CERRADO',
                    'monto_referencial_total' => 2800,
                    'observaciones' => 'Compra completa en un solo ciclo.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $sumarioId = fn (string $codigo): int => (int) DB::table('pbi_sumarios_cotizacion')->where('codigo', $codigo)->value('id');

            DB::table('pbi_sumario_items')->insert([
                [
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0001'),
                    'solicitud_item_id' => $solicitudItemId($solicitudId('SC-2026-0001'), $productoId('PBI-LAP-15')),
                    'producto_id' => $productoId('PBI-LAP-15'),
                    'cantidad_cotizada' => 6,
                    'precio_referencial' => 1180,
                    'subtotal_referencial' => 7080,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0001'),
                    'solicitud_item_id' => $solicitudItemId($solicitudId('SC-2026-0001'), $productoId('PBI-IMP-LSR')),
                    'producto_id' => $productoId('PBI-IMP-LSR'),
                    'cantidad_cotizada' => 3,
                    'precio_referencial' => 400,
                    'subtotal_referencial' => 1200,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0002'),
                    'solicitud_item_id' => $solicitudItemId($solicitudId('SC-2026-0001'), $productoId('PBI-LAP-15')),
                    'producto_id' => $productoId('PBI-LAP-15'),
                    'cantidad_cotizada' => 4,
                    'precio_referencial' => 1210,
                    'subtotal_referencial' => 4840,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0002'),
                    'solicitud_item_id' => $solicitudItemId($solicitudId('SC-2026-0001'), $productoId('PBI-IMP-LSR')),
                    'producto_id' => $productoId('PBI-IMP-LSR'),
                    'cantidad_cotizada' => 1,
                    'precio_referencial' => 430,
                    'subtotal_referencial' => 430,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0003'),
                    'solicitud_item_id' => $solicitudItemId($solicitudId('SC-2026-0002'), $productoId('PBI-ROD-IND')),
                    'producto_id' => $productoId('PBI-ROD-IND'),
                    'cantidad_cotizada' => 20,
                    'precio_referencial' => 138,
                    'subtotal_referencial' => 2760,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $sumarioItemId = fn (int $sumarioId, int $productoId): int => (int) DB::table('pbi_sumario_items')
                ->where('sumario_cotizacion_id', $sumarioId)
                ->where('producto_id', $productoId)
                ->orderBy('id')
                ->value('id');

            DB::table('pbi_ordenes_compra')->insert([
                [
                    'codigo' => 'OC-2026-0001',
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0001'),
                    'proveedor_id' => $proveedorId('J-40123456-1'),
                    'comprador_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'fecha_emision' => '2026-04-04',
                    'fecha_compromiso_entrega' => '2026-04-11',
                    'fecha_entrega_real' => '2026-04-12',
                    'estado' => 'FACTURA_CARGADA',
                    'monto_subtotal' => 7080,
                    'monto_impuestos' => 1132.8,
                    'monto_total' => 8212.8,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'codigo' => 'OC-2026-0002',
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0001'),
                    'proveedor_id' => $proveedorId('J-40999999-2'),
                    'comprador_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'fecha_emision' => '2026-04-04',
                    'fecha_compromiso_entrega' => '2026-04-13',
                    'fecha_entrega_real' => '2026-04-13',
                    'estado' => 'FACTURA_CARGADA',
                    'monto_subtotal' => 1200,
                    'monto_impuestos' => 192,
                    'monto_total' => 1392,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'codigo' => 'OC-2026-0003',
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0002'),
                    'proveedor_id' => $proveedorId('J-40123456-1'),
                    'comprador_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'fecha_emision' => '2026-04-16',
                    'fecha_compromiso_entrega' => '2026-04-24',
                    'fecha_entrega_real' => null,
                    'estado' => 'FACTURA_CARGADA',
                    'monto_subtotal' => 5270,
                    'monto_impuestos' => 843.2,
                    'monto_total' => 6113.2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'codigo' => 'OC-2026-0004',
                    'sumario_cotizacion_id' => $sumarioId('SUM-2026-0003'),
                    'proveedor_id' => $proveedorId('J-41234567-3'),
                    'comprador_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'fecha_emision' => '2026-04-08',
                    'fecha_compromiso_entrega' => '2026-04-15',
                    'fecha_entrega_real' => '2026-04-15',
                    'estado' => 'FACTURA_CARGADA',
                    'monto_subtotal' => 2760,
                    'monto_impuestos' => 441.6,
                    'monto_total' => 3201.6,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $ordenId = fn (string $codigo): int => (int) DB::table('pbi_ordenes_compra')->where('codigo', $codigo)->value('id');

            DB::table('pbi_orden_compra_items')->insert([
                [
                    'orden_compra_id' => $ordenId('OC-2026-0001'),
                    'sumario_item_id' => $sumarioItemId($sumarioId('SUM-2026-0001'), $productoId('PBI-LAP-15')),
                    'producto_id' => $productoId('PBI-LAP-15'),
                    'cantidad_ordenada' => 6,
                    'precio_unitario' => 1180,
                    'subtotal' => 7080,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0002'),
                    'sumario_item_id' => $sumarioItemId($sumarioId('SUM-2026-0001'), $productoId('PBI-IMP-LSR')),
                    'producto_id' => $productoId('PBI-IMP-LSR'),
                    'cantidad_ordenada' => 3,
                    'precio_unitario' => 400,
                    'subtotal' => 1200,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0003'),
                    'sumario_item_id' => $sumarioItemId($sumarioId('SUM-2026-0002'), $productoId('PBI-LAP-15')),
                    'producto_id' => $productoId('PBI-LAP-15'),
                    'cantidad_ordenada' => 4,
                    'precio_unitario' => 1210,
                    'subtotal' => 4840,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0003'),
                    'sumario_item_id' => $sumarioItemId($sumarioId('SUM-2026-0002'), $productoId('PBI-IMP-LSR')),
                    'producto_id' => $productoId('PBI-IMP-LSR'),
                    'cantidad_ordenada' => 1,
                    'precio_unitario' => 430,
                    'subtotal' => 430,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0004'),
                    'sumario_item_id' => $sumarioItemId($sumarioId('SUM-2026-0003'), $productoId('PBI-ROD-IND')),
                    'producto_id' => $productoId('PBI-ROD-IND'),
                    'cantidad_ordenada' => 20,
                    'precio_unitario' => 138,
                    'subtotal' => 2760,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('pbi_pagos_finanzas')->insert([
                [
                    'orden_compra_id' => $ordenId('OC-2026-0001'),
                    'fecha_programada_pago' => '2026-04-05',
                    'fecha_pago' => '2026-04-06',
                    'pagado_por_user_id' => $usuarioId('carlos.finanzas@demo.local'),
                    'estado_pago' => 'PAGADO',
                    'metodo_pago' => 'TRANSFERENCIA',
                    'referencia_pago' => 'TRX-OC1-7781',
                    'monto_pagado' => 8212.8,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0002'),
                    'fecha_programada_pago' => '2026-04-05',
                    'fecha_pago' => '2026-04-06',
                    'pagado_por_user_id' => $usuarioId('carlos.finanzas@demo.local'),
                    'estado_pago' => 'PAGADO',
                    'metodo_pago' => 'TRANSFERENCIA',
                    'referencia_pago' => 'TRX-OC2-7782',
                    'monto_pagado' => 1392,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0003'),
                    'fecha_programada_pago' => '2026-04-18',
                    'fecha_pago' => '2026-04-19',
                    'pagado_por_user_id' => $usuarioId('carlos.finanzas@demo.local'),
                    'estado_pago' => 'PAGADO',
                    'metodo_pago' => 'TRANSFERENCIA',
                    'referencia_pago' => 'TRX-OC3-7783',
                    'monto_pagado' => 6113.2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0004'),
                    'fecha_programada_pago' => '2026-04-09',
                    'fecha_pago' => '2026-04-10',
                    'pagado_por_user_id' => $usuarioId('carlos.finanzas@demo.local'),
                    'estado_pago' => 'PAGADO',
                    'metodo_pago' => 'TRANSFERENCIA',
                    'referencia_pago' => 'TRX-OC4-7784',
                    'monto_pagado' => 3201.6,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('pbi_recepciones_procura')->insert([
                [
                    'orden_compra_id' => $ordenId('OC-2026-0001'),
                    'fecha_recepcion_procura' => '2026-04-12',
                    'recibido_procura_por_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'tipo_documento_recepcion' => 'NOTA_ENTREGA',
                    'estado_recepcion_procura' => 'RECIBIDO',
                    'observaciones' => 'Entrega completa por proveedor.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0002'),
                    'fecha_recepcion_procura' => '2026-04-13',
                    'recibido_procura_por_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'tipo_documento_recepcion' => 'FACTURA',
                    'estado_recepcion_procura' => 'RECIBIDO',
                    'observaciones' => 'Incluye factura y guia.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0003'),
                    'fecha_recepcion_procura' => '2026-04-24',
                    'recibido_procura_por_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'tipo_documento_recepcion' => 'NOTA_ENTREGA',
                    'estado_recepcion_procura' => 'RECIBIDO',
                    'observaciones' => 'Recepcion parcial entregada a almacen y pendiente conformidad del solicitante.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0004'),
                    'fecha_recepcion_procura' => '2026-04-15',
                    'recibido_procura_por_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'tipo_documento_recepcion' => 'FACTURA',
                    'estado_recepcion_procura' => 'RECIBIDO',
                    'observaciones' => 'Recepcion sin novedades.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('pbi_entregas_almacen')->insert([
                [
                    'orden_compra_id' => $ordenId('OC-2026-0001'),
                    'fecha_entrega_almacen' => '2026-04-12',
                    'recibido_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                    'estado_entrega_almacen' => 'EN_TRANSICION',
                    'porcentaje_cumplimiento' => 100,
                    'observaciones' => 'Pendiente revision final del solicitante.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0003'),
                    'fecha_entrega_almacen' => '2026-04-24',
                    'recibido_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                    'estado_entrega_almacen' => 'EN_TRANSICION',
                    'porcentaje_cumplimiento' => 100,
                    'observaciones' => 'Material en zona de transicion pendiente de revision del solicitante.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0002'),
                    'fecha_entrega_almacen' => '2026-04-13',
                    'recibido_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                    'estado_entrega_almacen' => 'VALIDADA',
                    'porcentaje_cumplimiento' => 100,
                    'observaciones' => 'Producto conforme y listo para entrada.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0004'),
                    'fecha_entrega_almacen' => '2026-04-15',
                    'recibido_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                    'estado_entrega_almacen' => 'VALIDADA',
                    'porcentaje_cumplimiento' => 95,
                    'observaciones' => 'Se detecto 1 unidad con pequeno detalle de empaque.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('pbi_revision_solicitante')->insert([
                [
                    'orden_compra_id' => $ordenId('OC-2026-0001'),
                    'fecha_revision' => '2026-04-14',
                    'solicitante_user_id' => $usuarioId('ana.solicitante@demo.local'),
                    'decision' => 'RECHAZADO',
                    'motivo_rechazo' => 'Dos equipos con especificacion de RAM inferior.',
                    'fecha_devolucion_proveedor' => '2026-04-15',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0002'),
                    'fecha_revision' => '2026-04-14',
                    'solicitante_user_id' => $usuarioId('ana.solicitante@demo.local'),
                    'decision' => 'ACEPTADO',
                    'motivo_rechazo' => null,
                    'fecha_devolucion_proveedor' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0004'),
                    'fecha_revision' => '2026-04-16',
                    'solicitante_user_id' => $usuarioId('ana.solicitante@demo.local'),
                    'decision' => 'ACEPTADO',
                    'motivo_rechazo' => null,
                    'fecha_devolucion_proveedor' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('pbi_documentacion_administracion')->insert([
                [
                    'orden_compra_id' => $ordenId('OC-2026-0001'),
                    'fecha_entrega_administracion' => '2026-04-12',
                    'entregado_por_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'estado_documentacion' => 'ENTREGADA',
                    'observaciones' => 'Factura entregada, pendiente nota de credito por devolucion parcial.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0002'),
                    'fecha_entrega_administracion' => '2026-04-13',
                    'entregado_por_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'estado_documentacion' => 'ENTREGADA',
                    'observaciones' => 'Documentacion completa.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0004'),
                    'fecha_entrega_administracion' => '2026-04-15',
                    'entregado_por_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                    'estado_documentacion' => 'ENTREGADA',
                    'observaciones' => 'Documentacion completa.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $docId = fn (string $ocCodigo): int => (int) DB::table('pbi_documentacion_administracion')
                ->where('orden_compra_id', $ordenId($ocCodigo))
                ->value('id');

            DB::table('pbi_facturas_finanzas')->insert([
                [
                    'orden_compra_id' => $ordenId('OC-2026-0001'),
                    'documentacion_administracion_id' => $docId('OC-2026-0001'),
                    'numero_factura' => $facturaNumero(128),
                    'fecha_factura' => '2026-04-12',
                    'fecha_recepcion_finanzas' => '2026-04-12',
                    'fecha_carga_factura' => '2026-04-16',
                    'monto_base' => 7080,
                    'monto_impuestos' => 1132.8,
                    'monto_total' => 8212.8,
                    'estado_finanzas' => 'CARGADA_CON_AJUSTE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0002'),
                    'documentacion_administracion_id' => $docId('OC-2026-0002'),
                    'numero_factura' => $facturaNumero(129),
                    'fecha_factura' => '2026-04-13',
                    'fecha_recepcion_finanzas' => '2026-04-13',
                    'fecha_carga_factura' => '2026-04-14',
                    'monto_base' => 1200,
                    'monto_impuestos' => 192,
                    'monto_total' => 1392,
                    'estado_finanzas' => 'CARGADA',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'orden_compra_id' => $ordenId('OC-2026-0004'),
                    'documentacion_administracion_id' => $docId('OC-2026-0004'),
                    'numero_factura' => $facturaNumero(455),
                    'fecha_factura' => '2026-04-15',
                    'fecha_recepcion_finanzas' => '2026-04-15',
                    'fecha_carga_factura' => '2026-04-16',
                    'monto_base' => 2760,
                    'monto_impuestos' => 441.6,
                    'monto_total' => 3201.6,
                    'estado_finanzas' => 'CARGADA',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // Escala adicional: 25 solicitudes nuevas con su flujo de procura/finanzas.
            $productCodes = [
                'PBI-LAP-15',
                'PBI-IMP-LSR',
                'PBI-TON-85A',
                'PBI-ROD-IND',
                'PBI-TAB-OPS',
                'PBI-RAD-COM',
                'PBI-HER-KIT',
                'PBI-SIL-ERG',
                'PBI-PROY-TH',
                'PBI-KIT-IND',
                'PBI-CAS-SEG',
                'PBI-GAF-IND',
                'PBI-BOT-SEG',
                'PBI-CHA-REF',
                'PBI-LUB-IND',
                'PBI-COR-MTR',
                'PBI-SEN-TEMP',
                'PBI-LLV-KIT',
                'PBI-PHM-MTR',
                'PBI-BAL-PRC',
                'PBI-KIT-MUE',
                'PBI-TERM-IR',
            ];
            $providerRifs = ['J-48901234-0', 'J-44567890-6', 'J-40123456-1', 'J-40999999-2', 'J-41234567-3', 'J-42345678-4', 'J-43456789-5', 'J-45678901-7', 'J-46789012-8', 'J-47890123-9', 'J-48901234-0', 'J-44567890-6'];
            $deptosSolicitantes = ['OPERACIONES', 'TALENTO HUMANO', 'S.I.H.O', 'MANTENIMIENTO', 'CALIDAD'];
            $usuariosPorDepto = [
                'OPERACIONES' => ['ana.solicitante@demo.local'],
                'TALENTO HUMANO' => ['luis.th@demo.local'],
                'S.I.H.O' => ['marta.siho@demo.local'],
                'MANTENIMIENTO' => ['jose.mant@demo.local'],
                'CALIDAD' => ['sofia.calidad@demo.local'],
            ];
            $analistasProcura = ['pedro.procura@demo.local', 'laura.procura@demo.local', 'diego.procura@demo.local'];
            $cumplimientoPorProveedor = [
                'J-40123456-1' => 100,
                'J-40999999-2' => 80,
                'J-41234567-3' => 50,
                'J-42345678-4' => 30,
                'J-44567890-6' => 75,
                'J-48901234-0' => 85,
            ];
            $ocSinFacturaCargada = ['6-1', '12-1', '18-1', '24-1'];
            $sumarioSeq = 4;
            $ocSeq = 5;
            $facturaSeq = 500;
            $facturasLoopObjetivo = 14;
            $facturasLoopGeneradas = 0;

            for ($n = 3; $n <= 27; $n++) {
                $fechaSolicitud = \Carbon\Carbon::create(2026, 5, 1)->addDays($n - 3);
                $estadoSolicitud = $n % 6 === 0 ? 'EN_PROCESO' : 'COMPLETADA';
                $deptoSolicitudNombre = $deptosSolicitantes[$n % count($deptosSolicitantes)];
                $emailSolicitante = $usuariosPorDepto[$deptoSolicitudNombre][0];

                $solicitudCompraId = (int) DB::table('pbi_solicitudes_compra')->insertGetId([
                    'codigo' => sprintf('SC-2026-%04d', $n),
                    'fecha_solicitud' => $fechaSolicitud->toDateString(),
                    'solicitante_user_id' => $usuarioId($emailSolicitante),
                    'departamento_solicitante_id' => $deptoId($deptoSolicitudNombre),
                    'prioridad' => $n % 3 === 0 ? 'ALTA' : 'MEDIA',
                    'estado' => $estadoSolicitud,
                    'monto_estimado_total' => 0,
                    'fecha_requerida' => $fechaSolicitud->copy()->addDays(12)->toDateString(),
                    'aprobada_almacen_at' => $fechaSolicitud->copy()->addDay()->setTime(9, 0, 0),
                    'aprobada_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $items = [];
                $montoEstimado = 0.0;
                $itemCount = $n % 3 === 0 ? 2 : 1;

                for ($k = 0; $k < $itemCount; $k++) {
                    $codigoProducto = $productCodes[($n + $k) % count($productCodes)];
                    $productoIdNuevo = $productoId($codigoProducto);
                    $costo = (float) DB::table('pbi_productos')->where('id', $productoIdNuevo)->value('costo_referencia');
                    $cantidad = (float) (($n % 4) + $k + 1);
                    if ($deptoSolicitudNombre === 'CALIDAD' && $k === 0) {
                        $cantidad = 40;
                    }
                    $subtotal = round($cantidad * $costo, 2);
                    $montoEstimado += $subtotal;

                    $solicitudItemNuevoId = (int) DB::table('pbi_solicitud_items')->insertGetId([
                        'solicitud_compra_id' => $solicitudCompraId,
                        'producto_id' => $productoIdNuevo,
                        'descripcion' => 'Item generado para solicitud de escala ' . $n,
                        'cantidad' => $cantidad,
                        'costo_estimado_unitario' => $costo,
                        'subtotal_estimado' => $subtotal,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $items[] = [
                        'id' => $solicitudItemNuevoId,
                        'producto_id' => $productoIdNuevo,
                        'cantidad' => $cantidad,
                        'costo' => $costo,
                    ];
                }

                DB::table('pbi_solicitudes_compra')
                    ->where('id', $solicitudCompraId)
                    ->update([
                        'monto_estimado_total' => round($montoEstimado, 2),
                        'updated_at' => $now,
                    ]);

                $sumarioCount = ($n % 3 === 0 || $n === 25) ? 2 : 1;

                for ($s = 1; $s <= $sumarioCount; $s++) {
                    $itemSeleccionado = $items[($s - 1) % count($items)];
                    $cantidadCotizada = max(1, $itemSeleccionado['cantidad'] - ($sumarioCount === 2 && $s === 1 ? 1 : 0));
                    $factor = 1 + (((($n + $s) % 7) - 3) / 100);
                    $precioReferencial = round($itemSeleccionado['costo'] * $factor, 2);
                    $subtotalReferencial = round($cantidadCotizada * $precioReferencial, 2);

                    $fechaSumario = $fechaSolicitud->copy()->addDays(2 + (($n + $s) % 2));
                    $analistaEmail = $analistasProcura[($n + $s) % count($analistasProcura)];

                    $sumarioCotizacionId = (int) DB::table('pbi_sumarios_cotizacion')->insertGetId([
                        'codigo' => sprintf('SUM-2026-%04d', $sumarioSeq++),
                        'solicitud_compra_id' => $solicitudCompraId,
                        'analista_procura_user_id' => $usuarioId($analistaEmail),
                        'fecha_sumario' => $fechaSumario->toDateString(),
                        'estado' => $sumarioCount === 2 && $s === 1 ? 'CERRADO_PARCIAL' : 'CERRADO',
                        'monto_referencial_total' => $subtotalReferencial,
                        'observaciones' => 'Sumario generado automaticamente para escala de datos.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $sumarioItemNuevoId = (int) DB::table('pbi_sumario_items')->insertGetId([
                        'sumario_cotizacion_id' => $sumarioCotizacionId,
                        'solicitud_item_id' => $itemSeleccionado['id'],
                        'producto_id' => $itemSeleccionado['producto_id'],
                        'cantidad_cotizada' => $cantidadCotizada,
                        'precio_referencial' => $precioReferencial,
                        'subtotal_referencial' => $subtotalReferencial,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $ocClave = $n . '-' . $s;
                    $estadoOc = in_array($ocClave, $ocSinFacturaCargada, true)
                        ? match (($n + $s) % 3) {
                            0 => 'RECIBIDA_PROCURA',
                            1 => 'PAGADA',
                            default => 'EMITIDA',
                        }
                        : 'FACTURA_CARGADA';

                    $fechaEmision = $fechaSumario->copy()->addDay();
                    $rifProveedor = $providerRifs[($n + $s) % count($providerRifs)];
                    $ajusteMontoProv = match ($rifProveedor) {
                        'J-48901234-0' => 1.35,
                        'J-44567890-6' => 1.20,
                        'J-46789012-8' => 1.05,
                        default => 0.95,
                    };

                    $montoSubtotal = round($subtotalReferencial * $ajusteMontoProv, 2);
                    $montoImpuestos = round($montoSubtotal * 0.16, 2);
                    $montoTotal = round($montoSubtotal + $montoImpuestos, 2);

                    $ordenCompraNuevaId = (int) DB::table('pbi_ordenes_compra')->insertGetId([
                        'codigo' => sprintf('OC-2026-%04d', $ocSeq++),
                        'sumario_cotizacion_id' => $sumarioCotizacionId,
                        'proveedor_id' => $proveedorId($rifProveedor),
                        'comprador_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                        'fecha_emision' => $fechaEmision->toDateString(),
                        'fecha_compromiso_entrega' => $fechaEmision->copy()->addDays(5)->toDateString(),
                        'fecha_entrega_real' => $estadoOc === 'EMITIDA' ? null : $fechaEmision->copy()->addDays(6)->toDateString(),
                        'estado' => $estadoOc,
                        'monto_subtotal' => $montoSubtotal,
                        'monto_impuestos' => $montoImpuestos,
                        'monto_total' => $montoTotal,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('pbi_orden_compra_items')->insert([
                        'orden_compra_id' => $ordenCompraNuevaId,
                        'sumario_item_id' => $sumarioItemNuevoId,
                        'producto_id' => $itemSeleccionado['producto_id'],
                        'cantidad_ordenada' => $cantidadCotizada,
                        'precio_unitario' => $precioReferencial,
                        'subtotal' => $subtotalReferencial,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if (in_array($estadoOc, ['PAGADA', 'FACTURA_CARGADA'], true)) {
                        DB::table('pbi_pagos_finanzas')->insert([
                            'orden_compra_id' => $ordenCompraNuevaId,
                            'fecha_programada_pago' => $fechaEmision->copy()->addDays(2)->toDateString(),
                            'fecha_pago' => $fechaEmision->copy()->addDays(3)->toDateString(),
                            'pagado_por_user_id' => $usuarioId('carlos.finanzas@demo.local'),
                            'estado_pago' => 'PAGADO',
                            'metodo_pago' => 'TRANSFERENCIA',
                            'referencia_pago' => 'TRX-ESC-' . $n . '-' . $s,
                            'monto_pagado' => $montoTotal,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    if ($estadoOc !== 'EMITIDA') {
                        $cumplBase = $cumplimientoPorProveedor[$rifProveedor] ?? 85;
                        $cumplAjustado = max(30, min(100, $cumplBase + ((($n + $s) % 5) - 2) * 2));
                        $enTransicion = (($n + $s) % 3 === 0) || $estadoOc === 'RECIBIDA_PROCURA';
                        $fechaEntregaAlmacen = $enTransicion
                            ? now()->copy()->subDays(4 + (($n + $s) % 7))->toDateString()
                            : $fechaEmision->copy()->addDays(5)->toDateString();

                        DB::table('pbi_recepciones_procura')->insert([
                            'orden_compra_id' => $ordenCompraNuevaId,
                            'fecha_recepcion_procura' => $fechaEmision->copy()->addDays(5)->toDateString(),
                            'recibido_procura_por_user_id' => $usuarioId('pedro.procura@demo.local'),
                            'tipo_documento_recepcion' => 'NOTA_ENTREGA',
                            'estado_recepcion_procura' => 'RECIBIDO',
                            'observaciones' => 'Recepcion generada automaticamente.',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    if (in_array($estadoOc, ['RECIBIDA_PROCURA', 'PAGADA', 'FACTURA_CARGADA'], true)) {
                        $estadoEntrega = $enTransicion ? 'EN_TRANSICION' : 'VALIDADA';
                        DB::table('pbi_entregas_almacen')->insert([
                            'orden_compra_id' => $ordenCompraNuevaId,
                            'fecha_entrega_almacen' => $fechaEntregaAlmacen,
                            'recibido_almacen_por_user_id' => $usuarioId('luisa.almacen@demo.local'),
                            'estado_entrega_almacen' => $estadoEntrega,
                            'porcentaje_cumplimiento' => $enTransicion ? max(40, $cumplAjustado - 8) : $cumplAjustado,
                            'observaciones' => 'Entrega de almacen generada automaticamente.',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    if (in_array($estadoOc, ['RECIBIDA_PROCURA', 'FACTURA_CARGADA'], true)) {
                        DB::table('pbi_revision_solicitante')->insert([
                            'orden_compra_id' => $ordenCompraNuevaId,
                            'fecha_revision' => $fechaEmision->copy()->addDays(9)->toDateString(),
                            'solicitante_user_id' => $usuarioId($emailSolicitante),
                            'decision' => (($n + $s) % 5 === 0) ? 'RECHAZADO' : 'ACEPTADO',
                            'motivo_rechazo' => (($n + $s) % 5 === 0) ? 'Observacion de calidad en revision automatica.' : null,
                            'fecha_devolucion_proveedor' => (($n + $s) % 5 === 0) ? $fechaEmision->copy()->addDays(10)->toDateString() : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $documentacionId = null;
                    if (in_array($estadoOc, ['PAGADA', 'FACTURA_CARGADA'], true)) {
                        $documentacionId = (int) DB::table('pbi_documentacion_administracion')->insertGetId([
                            'orden_compra_id' => $ordenCompraNuevaId,
                            'fecha_entrega_administracion' => $fechaEmision->copy()->addDays(8)->toDateString(),
                            'entregado_por_procura_user_id' => $usuarioId('pedro.procura@demo.local'),
                            'estado_documentacion' => 'ENTREGADA',
                            'observaciones' => 'Documentacion generada automaticamente para escala.',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    if ($estadoOc === 'FACTURA_CARGADA' && $documentacionId && $facturasLoopGeneradas < $facturasLoopObjetivo) {
                        DB::table('pbi_facturas_finanzas')->insert([
                            'orden_compra_id' => $ordenCompraNuevaId,
                            'documentacion_administracion_id' => $documentacionId,
                            'numero_factura' => $facturaNumero($facturaSeq++),
                            'fecha_factura' => $fechaEmision->copy()->addDays(2)->toDateString(),
                            'fecha_recepcion_finanzas' => $fechaEmision->copy()->addDays(2)->toDateString(),
                            'fecha_carga_factura' => $fechaEmision->copy()->addDays(3)->toDateString(),
                            'monto_base' => $montoSubtotal,
                            'monto_impuestos' => $montoImpuestos,
                            'monto_total' => $montoTotal,
                            'estado_finanzas' => 'CARGADA',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $facturasLoopGeneradas++;
                    }
                }
            }
        });

        $this->command?->info('PowerBiMiniFlowSeeder ejecutado correctamente.');
    }
}
