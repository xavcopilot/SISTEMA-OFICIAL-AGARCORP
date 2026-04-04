<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\MovementItem;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryStressTest extends Command
{
    protected $signature = 'inventario:stress-test
        {--batches=1 : Numero de lotes a ejecutar}
        {--productos=100 : Productos nuevos por lote}
        {--entradas=100 : Movimientos de entrada por lote}
        {--salidas=100 : Movimientos de salida por lote}
        {--max-items=3 : Maximo de lineas por movimiento}
        {--user-id= : ID de usuario para created_by_user_id}
        {--cleanup : Elimina al final los datos generados por esta corrida}';

    protected $description = 'Prueba de carga para inventario: crea productos, entradas y salidas por lotes y reporta tiempos.';

    private string $runTag;

    private int $subcategoryId;

    private ?int $createdByUserId;

    private array $generatedProductIds = [];

    private array $generatedMovementIds = [];

    private array $generatedItemIds = [];

    private array $productStocks = [];

    public function handle(): int
    {
        $batches = max(1, (int) $this->option('batches'));
        $productosPorLote = max(0, (int) $this->option('productos'));
        $entradasPorLote = max(0, (int) $this->option('entradas'));
        $salidasPorLote = max(0, (int) $this->option('salidas'));
        $maxItemsPorMovimiento = max(1, (int) $this->option('max-items'));
        $this->createdByUserId = $this->option('user-id') !== null
            ? max(1, (int) $this->option('user-id'))
            : null;

        if ($productosPorLote === 0 && $entradasPorLote === 0 && $salidasPorLote === 0) {
            $this->warn('No hay trabajo que ejecutar. Ajusta las opciones --productos, --entradas o --salidas.');

            return self::INVALID;
        }

        $this->runTag = now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
        $this->subcategoryId = $this->resolveStressSubcategoryId();

        $countsBefore = $this->currentCounts();
        $globalStart = microtime(true);

        $this->info('Iniciando prueba de carga de inventario');
        $this->line('Run tag: ' . $this->runTag);
        $this->line('Batches: ' . $batches);
        $this->line('Por lote -> productos: ' . $productosPorLote . ', entradas: ' . $entradasPorLote . ', salidas: ' . $salidasPorLote);
        $this->newLine();

        $totals = [
            'products' => 0,
            'entradas' => 0,
            'salidas' => 0,
            'items' => 0,
            'salidas_saltadas' => 0,
            't_productos' => 0.0,
            't_entradas' => 0.0,
            't_salidas' => 0.0,
            't_lotes' => 0.0,
        ];

        for ($batch = 1; $batch <= $batches; $batch++) {
            $batchStart = microtime(true);

            $batchMetrics = DB::transaction(function () use ($batch, $productosPorLote, $entradasPorLote, $salidasPorLote, $maxItemsPorMovimiento): array {
                $createdProducts = $this->createProductsBatch($batch, $productosPorLote);
                $entradaMetrics = $this->createEntradaMovementsBatch($batch, $entradasPorLote, $maxItemsPorMovimiento);
                $salidaMetrics = $this->createSalidaMovementsBatch($batch, $salidasPorLote, $maxItemsPorMovimiento);

                return [
                    'created_products' => $createdProducts['count'],
                    't_productos' => $createdProducts['seconds'],
                    'entradas' => $entradaMetrics['movements'],
                    't_entradas' => $entradaMetrics['seconds'],
                    'salidas' => $salidaMetrics['movements'],
                    't_salidas' => $salidaMetrics['seconds'],
                    'items' => $entradaMetrics['items'] + $salidaMetrics['items'],
                    'salidas_saltadas' => $salidaMetrics['skipped'],
                ];
            }, 3);

            $batchSeconds = microtime(true) - $batchStart;
            $totals['products'] += $batchMetrics['created_products'];
            $totals['entradas'] += $batchMetrics['entradas'];
            $totals['salidas'] += $batchMetrics['salidas'];
            $totals['items'] += $batchMetrics['items'];
            $totals['salidas_saltadas'] += $batchMetrics['salidas_saltadas'];
            $totals['t_productos'] += $batchMetrics['t_productos'];
            $totals['t_entradas'] += $batchMetrics['t_entradas'];
            $totals['t_salidas'] += $batchMetrics['t_salidas'];
            $totals['t_lotes'] += $batchSeconds;

            $this->line(sprintf(
                'Lote %d -> productos: %d, entradas: %d, salidas: %d, items: %d, t_lote: %.3fs',
                $batch,
                $batchMetrics['created_products'],
                $batchMetrics['entradas'],
                $batchMetrics['salidas'],
                $batchMetrics['items'],
                $batchSeconds
            ));
        }

        $totalSeconds = microtime(true) - $globalStart;
        $countsAfter = $this->currentCounts();

        $this->newLine();
        $this->info('Resumen de carga');
        $this->table(
            ['Metric', 'Valor'],
            [
                ['Run tag', $this->runTag],
                ['Productos creados', (string) $totals['products']],
                ['Entradas creadas', (string) $totals['entradas']],
                ['Salidas creadas', (string) $totals['salidas']],
                ['Items creados', (string) $totals['items']],
                ['Salidas saltadas por stock', (string) $totals['salidas_saltadas']],
                ['Tiempo productos (s)', number_format($totals['t_productos'], 3)],
                ['Tiempo entradas (s)', number_format($totals['t_entradas'], 3)],
                ['Tiempo salidas (s)', number_format($totals['t_salidas'], 3)],
                ['Tiempo total lotes (s)', number_format($totals['t_lotes'], 3)],
                ['Tiempo total script (s)', number_format($totalSeconds, 3)],
            ]
        );

        $this->newLine();
        $this->info('Crecimiento en tablas');
        $this->table(
            ['Tabla', 'Antes', 'Despues', 'Delta'],
            [
                ['products', (string) $countsBefore['products'], (string) $countsAfter['products'], (string) ($countsAfter['products'] - $countsBefore['products'])],
                ['inventory_movements', (string) $countsBefore['movements'], (string) $countsAfter['movements'], (string) ($countsAfter['movements'] - $countsBefore['movements'])],
                ['movement_items', (string) $countsBefore['items'], (string) $countsAfter['items'], (string) ($countsAfter['items'] - $countsBefore['items'])],
            ]
        );

        $totalRows = $totals['products'] + $totals['entradas'] + $totals['salidas'] + $totals['items'];
        $rowsPerSecond = $totalSeconds > 0 ? $totalRows / $totalSeconds : 0.0;
        $this->line('Throughput aprox. (filas/s): ' . number_format($rowsPerSecond, 2));

        if ((bool) $this->option('cleanup')) {
            $this->newLine();
            $this->warn('Ejecutando cleanup de datos generados por esta corrida...');
            $cleanupSeconds = $this->cleanupGeneratedData();
            $this->line('Cleanup completado en ' . number_format($cleanupSeconds, 3) . 's');
        }

        return self::SUCCESS;
    }

    private function createProductsBatch(int $batch, int $count): array
    {
        $start = microtime(true);

        for ($i = 1; $i <= $count; $i++) {
            $serial = 'ST-' . $this->runTag . '-B' . $batch . '-P' . $i;

            $product = Product::create([
                'cod_ingreso' => 'STR-' . $this->runTag,
                'descripcion' => 'Producto stress ' . $this->runTag . ' lote ' . $batch . ' item ' . $i,
                'marca' => 'TEST',
                'subcategory_id' => $this->subcategoryId,
                'serial' => $serial,
                'estado' => 'NUEVO',
                'medida' => 'UND',
                'ubicacion' => 'ALMACEN STRESS',
                'dpto_responsable' => 'ALMACEN',
                'stock_minimo' => random_int(1, 5),
                'stock_actual' => 0,
                'precio_unitario' => random_int(10, 500),
                'fecha_adquisicion' => now()->toDateString(),
                'fecha_ultima_entrada' => null,
                'fecha_ultima_salida' => null,
                'is_archived' => false,
            ]);

            $this->generatedProductIds[] = (int) $product->id;
            $this->productStocks[(int) $product->id] = 0;
        }

        return [
            'count' => $count,
            'seconds' => microtime(true) - $start,
        ];
    }

    private function createEntradaMovementsBatch(int $batch, int $movements, int $maxItems): array
    {
        $start = microtime(true);
        $itemsCreated = 0;

        if ($movements <= 0) {
            return [
                'movements' => 0,
                'items' => 0,
                'seconds' => 0.0,
            ];
        }

        if ($this->generatedProductIds === []) {
            return [
                'movements' => 0,
                'items' => 0,
                'seconds' => microtime(true) - $start,
            ];
        }

        for ($n = 1; $n <= $movements; $n++) {
            $movement = InventoryMovement::create([
                'tipo' => 'entrada',
                'nro_control' => sprintf('EN-%s-B%02d-%04d', $this->runTag, $batch, $n),
                'orden_compra' => 'OC-STRESS-' . $batch,
                'nro_solicitud' => null,
                'factura_nota' => null,
                'nro_doc_legal' => null,
                'proveedor' => 'PROVEEDOR STRESS',
                'almacenista' => 'SCRIPT STRESS',
                'responsable_destino' => null,
                'dpto_destino' => null,
                'comentarios' => 'Stress test ' . $this->runTag,
                'solicitar_formato_entrada' => false,
                'total_items' => 0,
                'created_by_user_id' => $this->createdByUserId,
                'updated_by_user_id' => $this->createdByUserId,
            ]);

            $this->generatedMovementIds[] = (int) $movement->id;

            $lines = random_int(1, $maxItems);
            $movementTotal = 0;
            $usedInMovement = [];

            for ($line = 1; $line <= $lines; $line++) {
                $productId = $this->pickRandomProductId($usedInMovement);

                if ($productId === null) {
                    continue;
                }

                $usedInMovement[$productId] = true;
                $cantidad = random_int(1, 20);
                $movementTotal += $cantidad;

                $item = MovementItem::create([
                    'movement_id' => (int) $movement->id,
                    'product_id' => $productId,
                    'cantidad' => $cantidad,
                    'precio_momento' => random_int(10, 500),
                    'retorna' => false,
                    'observaciones_item' => null,
                ]);

                $this->generatedItemIds[] = (int) $item->id;
                $itemsCreated++;

                Product::query()
                    ->whereKey($productId)
                    ->update([
                        'stock_actual' => DB::raw('stock_actual + ' . $cantidad),
                        'fecha_ultima_entrada' => now()->toDateString(),
                    ]);

                $this->productStocks[$productId] = ($this->productStocks[$productId] ?? 0) + $cantidad;
            }

            $movement->update(['total_items' => $movementTotal]);
        }

        return [
            'movements' => $movements,
            'items' => $itemsCreated,
            'seconds' => microtime(true) - $start,
        ];
    }

    private function createSalidaMovementsBatch(int $batch, int $movements, int $maxItems): array
    {
        $start = microtime(true);
        $itemsCreated = 0;
        $createdMovements = 0;
        $skipped = 0;

        if ($movements <= 0) {
            return [
                'movements' => 0,
                'items' => 0,
                'skipped' => 0,
                'seconds' => 0.0,
            ];
        }

        for ($n = 1; $n <= $movements; $n++) {
            $eligible = array_keys(array_filter(
                $this->productStocks,
                fn (int $stock): bool => $stock > 0
            ));

            if ($eligible === []) {
                $skipped++;

                continue;
            }

            $movement = InventoryMovement::create([
                'tipo' => 'salida',
                'nro_control' => sprintf('SAL-%s-B%02d-%04d', $this->runTag, $batch, $n),
                'orden_compra' => null,
                'nro_solicitud' => null,
                'factura_nota' => null,
                'nro_doc_legal' => null,
                'proveedor' => null,
                'almacenista' => 'SCRIPT STRESS',
                'responsable_destino' => 'RESP STRESS',
                'dpto_destino' => 'ALMACEN',
                'comentarios' => 'Stress test ' . $this->runTag,
                'solicitar_formato_entrada' => false,
                'total_items' => 0,
                'created_by_user_id' => $this->createdByUserId,
                'updated_by_user_id' => $this->createdByUserId,
            ]);

            $createdMovements++;
            $this->generatedMovementIds[] = (int) $movement->id;

            $lines = random_int(1, $maxItems);
            $movementTotal = 0;
            $usedInMovement = [];

            for ($line = 1; $line <= $lines; $line++) {
                $productId = $this->pickRandomEligibleProductId($eligible, $usedInMovement);

                if ($productId === null) {
                    continue;
                }

                $stockDisponible = (int) ($this->productStocks[$productId] ?? 0);

                if ($stockDisponible <= 0) {
                    continue;
                }

                $usedInMovement[$productId] = true;

                $cantidad = random_int(1, $stockDisponible);
                $movementTotal += $cantidad;

                $item = MovementItem::create([
                    'movement_id' => (int) $movement->id,
                    'product_id' => $productId,
                    'cantidad' => $cantidad,
                    'precio_momento' => null,
                    'retorna' => (bool) random_int(0, 1),
                    'observaciones_item' => null,
                ]);

                $this->generatedItemIds[] = (int) $item->id;
                $itemsCreated++;

                Product::query()
                    ->whereKey($productId)
                    ->update([
                        'stock_actual' => DB::raw('GREATEST(stock_actual - ' . $cantidad . ', 0)'),
                        'fecha_ultima_salida' => now()->toDateString(),
                    ]);

                $this->productStocks[$productId] = max(0, $stockDisponible - $cantidad);
            }

            $movement->update(['total_items' => $movementTotal]);
        }

        return [
            'movements' => $createdMovements,
            'items' => $itemsCreated,
            'skipped' => $skipped,
            'seconds' => microtime(true) - $start,
        ];
    }

    private function resolveStressSubcategoryId(): int
    {
        $category = Category::query()->firstOrCreate(['name' => 'ALMACEN STRESS']);

        $subcategory = Subcategory::query()->firstOrCreate([
            'category_id' => (int) $category->id,
            'name' => 'GENERAL STRESS',
        ]);

        return (int) $subcategory->id;
    }

    private function pickRandomProductId(array $usedInMovement): ?int
    {
        if ($this->generatedProductIds === []) {
            return null;
        }

        $ids = array_values(array_filter(
            $this->generatedProductIds,
            fn (int $id): bool => ! isset($usedInMovement[$id])
        ));

        if ($ids === []) {
            return null;
        }

        return $ids[array_rand($ids)];
    }

    private function pickRandomEligibleProductId(array $eligible, array $usedInMovement): ?int
    {
        $ids = array_values(array_filter(
            $eligible,
            fn (int $id): bool => ! isset($usedInMovement[$id])
        ));

        if ($ids === []) {
            return null;
        }

        return $ids[array_rand($ids)];
    }

    private function currentCounts(): array
    {
        return [
            'products' => Product::query()->count(),
            'movements' => InventoryMovement::query()->count(),
            'items' => MovementItem::query()->count(),
        ];
    }

    private function cleanupGeneratedData(): float
    {
        $start = microtime(true);

        DB::transaction(function (): void {
            if ($this->generatedMovementIds !== []) {
                MovementItem::query()->whereIn('movement_id', $this->generatedMovementIds)->delete();
                InventoryMovement::query()->whereIn('id', $this->generatedMovementIds)->delete();
            }

            if ($this->generatedProductIds !== []) {
                Product::query()->whereIn('id', $this->generatedProductIds)->delete();
            }
        }, 3);

        return microtime(true) - $start;
    }
}
