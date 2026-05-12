<?php

namespace App\Support;

use App\Models\AlmacenAdvImport;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\InventarioEntradaImport;
use App\Models\InventarioSalidaImport;
use App\Models\MovementItem;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InventorySpreadsheetImportService
{
    public function normalizeAndBatchManualProducts(?string $batch = null): array
    {
        $requestedBatch = $this->nullableString($batch);
        $batch = $requestedBatch ?? $this->makeBatch('ALM');

        $stages = AlmacenAdvImport::query()
            ->whereNull('lote_importacion')
            ->where('procesado', false)
            ->get();

        if ($stages->isEmpty()) {
            $existingBatch = $requestedBatch
                ?? $this->latestPendingBatch(AlmacenAdvImport::class);

            return [
                'batch' => $existingBatch ?? $batch,
                'normalized' => 0,
            ];
        }

        $normalized = 0;

        foreach ($stages as $stage) {
            $stage->forceFill(['lote_importacion' => $batch])->save();

            $normalized++;
        }

        return [
            'batch' => $batch,
            'normalized' => $normalized,
        ];
    }

    public function normalizeAndBatchManualEntradas(?string $batch = null): array
    {
        $requestedBatch = $this->nullableString($batch);
        $batch = $requestedBatch ?? $this->makeBatch('ENT');

        $stages = InventarioEntradaImport::query()
            ->whereNull('lote_importacion')
            ->where('procesado', false)
            ->get();

        if ($stages->isEmpty()) {
            $existingBatch = $requestedBatch
                ?? $this->latestPendingBatch(InventarioEntradaImport::class);

            return [
                'batch' => $existingBatch ?? $batch,
                'normalized' => 0,
            ];
        }

        $normalized = 0;

        foreach ($stages as $stage) {
            $stage->forceFill(['lote_importacion' => $batch])->save();

            $normalized++;
        }

        return [
            'batch' => $batch,
            'normalized' => $normalized,
        ];
    }

    public function normalizeAndBatchManualSalidas(?string $batch = null): array
    {
        $requestedBatch = $this->nullableString($batch);
        $batch = $requestedBatch ?? $this->makeBatch('SAL');

        $stages = InventarioSalidaImport::query()
            ->whereNull('lote_importacion')
            ->where('procesado', false)
            ->get();

        if ($stages->isEmpty()) {
            $existingBatch = $requestedBatch
                ?? $this->latestPendingBatch(InventarioSalidaImport::class);

            return [
                'batch' => $existingBatch ?? $batch,
                'normalized' => 0,
            ];
        }

        $normalized = 0;

        foreach ($stages as $stage) {
            $stage->forceFill(['lote_importacion' => $batch])->save();

            $normalized++;
        }

        return [
            'batch' => $batch,
            'normalized' => $normalized,
        ];
    }

    public function processProductsBatch(string $batch): array
    {
        $stages = AlmacenAdvImport::query()
            ->where('lote_importacion', $batch)
            ->where('procesado', false)
            ->get();

        $processed = 0;
        $errors = [];

        foreach ($stages as $index => $stage) {
            try {
                DB::transaction(function () use ($stage, $batch, $index): void {
                    $stageData = $this->productStageData($stage);
                    $subcategory = $this->resolveSubcategory($stageData['categoria'], $stageData['subcatg']);
                    $fechaAdquisicion = $stageData['fecha_adquisicion'] ?? now()->toDateString();
                    $fechaUltimaEntrada = $stageData['fecha_ultima_entrada'] ?? $fechaAdquisicion;
                    $fechaUltimaSalida = $stageData['fecha_ultima_salida'];
                    $sku = $this->nullableString($stageData['sku']);

                    $attributes = [
                        'cod_ingreso' => 'IMP-' . $batch . '-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'descripcion' => (string) ($stageData['producto'] ?? ''),
                        'marca' => (string) ($stageData['marca'] ?? ''),
                        'subcategory_id' => $subcategory->id,
                        'serial' => $this->normalizeSerial($stageData['serial']),
                        'estado' => (string) ($stageData['estado'] ?? ''),
                        'medida' => (string) ($stageData['medida'] ?? ''),
                        'ubicacion' => (string) ($stageData['ubicacion'] ?? ''),
                        'dpto_responsable' => (string) ($stageData['dpto_responsable'] ?? ''),
                        'stock_minimo' => (int) ($stageData['min'] ?? 0),
                        'stock_actual' => (int) ($stageData['cant_total'] ?? 0),
                        'precio_unitario' => (float) ($stageData['p_unitario'] ?? 0),
                        'fecha_adquisicion' => $fechaAdquisicion,
                        'fecha_ultima_entrada' => $fechaUltimaEntrada,
                        'fecha_ultima_salida' => $fechaUltimaSalida,
                        'is_archived' => Str::lower((string) ($stageData['estado_registro'] ?? '')) === 'archivado',
                    ];

                    $product = $sku !== null
                        ? Product::query()->where('sku', $sku)->first()
                        : null;

                    if ($product) {
                        $product->fill($attributes);
                        $product->save();
                    } else {
                        if ($sku !== null) {
                            $attributes['sku'] = $sku;
                        }

                        $product = Product::query()->create($attributes);
                    }

                    $stage->forceFill([
                        'product_id' => $product->id,
                        'procesado' => true,
                        'procesado_en' => now(),
                        'error_importacion' => null,
                    ])->save();
                });

                $processed++;
            } catch (\Throwable $throwable) {
                $stage->forceFill([
                    'error_importacion' => $throwable->getMessage(),
                ])->save();

                $errors[] = 'Fila staging ' . $stage->id . ': ' . $throwable->getMessage();
            }
        }

        return $this->buildResult($batch, $stages->count(), $processed, $errors);
    }

    public function processEntradasBatch(string $batch, ?int $userId = null, ?string $userName = null): array
    {
        $stages = InventarioEntradaImport::query()
            ->where('lote_importacion', $batch)
            ->where('procesado', false)
            ->get();

        $processed = 0;
        $errors = [];

        foreach ($stages->groupBy(fn (InventarioEntradaImport $stage): string => (string) (($this->entradaStageData($stage)['nro_control']) ?: 'SIN-CONTROL-' . $stage->id)) as $groupKey => $group) {
            try {
                DB::transaction(function () use ($group, $groupKey, $userId, $userName): void {
                    if (InventoryMovement::query()->where('nro_control', $groupKey)->whereIn('tipo', ['ingreso', 'entrada'])->exists()) {
                        throw ValidationException::withMessages([
                            'nro_control' => 'Ya existe un movimiento de entrada/ingreso con el control ' . $groupKey . '.',
                        ]);
                    }

                    $movementType = $this->inferEntradaMovementType($group);
                    $fecha = $this->resolveDateFromStages($group, fn (InventarioEntradaImport $stage): ?string => $this->entradaStageData($stage)['fecha']);
                    $movementId = $this->insertInventoryMovement([
                        'tipo' => $movementType,
                        'fecha' => $fecha,
                        'nro_control' => $groupKey,
                        'orden_compra' => $this->firstResolvedValue($group, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['orden_compra']),
                        'nro_solicitud' => $this->firstResolvedValue($group, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['nro_solicitud']),
                        'factura_nota' => $this->firstResolvedValue($group, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['factura_nota']),
                        'nro_doc_legal' => $this->firstResolvedValue($group, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['nro_doc_legal']),
                        'proveedor' => $this->firstResolvedValue($group, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['proveedor']),
                        'almacenista' => $userName,
                        'comentarios' => $this->firstResolvedValue($group, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['comentario']),
                        'created_by_user_id' => $userId,
                        'total_items' => $group->count(),
                    ]);

                    foreach ($group->values() as $offset => $stage) {
                        $stageData = $this->entradaStageData($stage);
                        $cantidad = (int) ($stageData['cant'] ?? 0);
                        $precio = (float) ($stageData['precio'] ?? 0);

                        if ($cantidad <= 0) {
                            throw ValidationException::withMessages([
                                'cant' => 'La cantidad debe ser mayor a cero para el control ' . $groupKey . '.',
                            ]);
                        }

                        if ($movementType === 'ingreso') {
                            $subcategory = $this->resolveSubcategory($stageData['categoria'], $stageData['subcat']);
                            $product = Product::query()->create([
                                'sku' => $this->nullableString($stageData['sku']),
                                'cod_ingreso' => $groupKey . '-' . str_pad((string) ($offset + 1), 3, '0', STR_PAD_LEFT),
                                'descripcion' => (string) ($stageData['descripcion'] ?? ''),
                                'marca' => (string) ($stageData['marca'] ?? ''),
                                'subcategory_id' => $subcategory->id,
                                'serial' => $this->normalizeSerial($stageData['serial']),
                                'estado' => (string) ($stageData['estado'] ?? ''),
                                'medida' => (string) ($stageData['medida'] ?? ''),
                                'ubicacion' => (string) ($stageData['ubicacion'] ?? ''),
                                'dpto_responsable' => (string) ($stageData['dpto_responsible'] ?? ''),
                                'stock_minimo' => 0,
                                'stock_actual' => $cantidad,
                                'precio_unitario' => $precio,
                                'fecha_adquisicion' => $fecha,
                                'fecha_ultima_entrada' => $fecha,
                            ]);
                        } else {
                            $product = $this->findExistingProduct($stageData['sku'], $stageData['serial'], true);
                            $stockAnterior = (int) ($product->stock_actual ?? 0);
                            $precioAnterior = (float) ($product->precio_unitario ?? 0);
                            $stockNuevo = $stockAnterior + $cantidad;

                            $product->update([
                                'stock_actual' => $stockNuevo,
                                'precio_unitario' => $this->calculateWeightedAverageUnitPrice($stockAnterior, $precioAnterior, $cantidad, $precio),
                                'fecha_ultima_entrada' => $fecha,
                            ]);
                        }

                        $movementItem = MovementItem::query()->create([
                            'movement_id' => $movementId,
                            'product_id' => $product->id,
                            'cantidad' => $cantidad,
                            'precio_momento' => $precio,
                            'retorna' => false,
                            'observaciones_item' => $stageData['comentario'],
                        ]);

                        $stage->forceFill([
                            'inventory_movement_id' => $movementId,
                            'movement_item_id' => $movementItem->id,
                            'product_id' => $product->id,
                            'procesado' => true,
                            'procesado_en' => now(),
                            'error_importacion' => null,
                        ])->save();
                    }
                });

                $processed += $group->count();
            } catch (\Throwable $throwable) {
                InventarioEntradaImport::query()->whereIn('id', $group->pluck('id'))->update([
                    'error_importacion' => $throwable->getMessage(),
                ]);

                $errors[] = 'Control ' . $groupKey . ': ' . $throwable->getMessage();
            }
        }

        return $this->buildResult($batch, $stages->count(), $processed, $errors);
    }

    public function processSalidasBatch(string $batch, ?int $userId = null, ?string $userName = null): array
    {
        $stages = InventarioSalidaImport::query()
            ->where('lote_importacion', $batch)
            ->where('procesado', false)
            ->get();

        $processed = 0;
        $errors = [];

        foreach ($stages->groupBy(fn (InventarioSalidaImport $stage): string => (string) (($this->salidaStageData($stage)['nro_control']) ?: 'SIN-CONTROL-' . $stage->id)) as $groupKey => $group) {
            try {
                DB::transaction(function () use ($group, $groupKey, $userId, $userName): void {
                    if (InventoryMovement::query()->where('nro_control', $groupKey)->where('tipo', 'salida')->exists()) {
                        throw ValidationException::withMessages([
                            'nro_control' => 'Ya existe una salida con el control ' . $groupKey . '.',
                        ]);
                    }

                    $fecha = $this->resolveDateFromStages($group, fn (InventarioSalidaImport $stage): ?string => $this->salidaStageData($stage)['fecha']);
                    $movementId = $this->insertInventoryMovement([
                        'tipo' => 'salida',
                        'fecha' => $fecha,
                        'nro_control' => $groupKey,
                        'almacenista' => $this->firstResolvedValue($group, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['quien_entrega']) ?? $userName,
                        'responsable_destino' => $this->firstResolvedValue($group, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['responsable']),
                        'dpto_destino' => $this->firstResolvedValue($group, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['area_dpto']),
                        'comentarios' => $this->firstResolvedValue($group, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['observaciones']),
                        'created_by_user_id' => $userId,
                        'total_items' => $group->count(),
                    ]);

                    foreach ($group as $stage) {
                        $stageData = $this->salidaStageData($stage);
                        $product = $this->findExistingProduct($stageData['sku'], $stageData['serial'], true, true);
                        $cantidad = (int) ($stageData['cant'] ?? 0);

                        if ($cantidad <= 0) {
                            throw ValidationException::withMessages([
                                'cant' => 'La cantidad debe ser mayor a cero para el control ' . $groupKey . '.',
                            ]);
                        }

                        if ($cantidad > (int) $product->stock_actual) {
                            throw ValidationException::withMessages([
                                'cant' => 'No hay stock suficiente para el SKU ' . $product->sku . ' en el control ' . $groupKey . '.',
                            ]);
                        }

                        $precioMomento = (float) ($product->precio_unitario ?? 0);

                        $product->update([
                            'stock_actual' => (int) $product->stock_actual - $cantidad,
                            'fecha_ultima_salida' => $fecha,
                        ]);

                        $movementItem = MovementItem::query()->create([
                            'movement_id' => $movementId,
                            'product_id' => $product->id,
                            'cantidad' => $cantidad,
                            'precio_momento' => $precioMomento,
                            'retorna' => $this->parseRetornaFlag($stageData['retorna']),
                            'observaciones_item' => $stageData['observaciones'],
                        ]);

                        $stage->forceFill([
                            'inventory_movement_id' => $movementId,
                            'movement_item_id' => $movementItem->id,
                            'product_id' => $product->id,
                            'procesado' => true,
                            'procesado_en' => now(),
                            'error_importacion' => null,
                        ])->save();
                    }
                });

                $processed += $group->count();
            } catch (\Throwable $throwable) {
                InventarioSalidaImport::query()->whereIn('id', $group->pluck('id'))->update([
                    'error_importacion' => $throwable->getMessage(),
                ]);

                $errors[] = 'Control ' . $groupKey . ': ' . $throwable->getMessage();
            }
        }

        return $this->buildResult($batch, $stages->count(), $processed, $errors);
    }

    public function importProductsFromStoredFile(string $absolutePath, ?int $userId = null): array
    {
        $batch = $this->makeBatch('ALM');
        $rows = $this->readSpreadsheetRows($absolutePath);
        $staged = 0;
        $processed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $mapped = $this->mapProductRow($row);

            if ($this->rowIsEmpty($mapped, ['sku', 'producto', 'categoria', 'subcatg'])) {
                continue;
            }

            $staged++;

            $stage = AlmacenAdvImport::query()->create(array_merge($this->toProductStagePayload($mapped), [
                'lote_importacion' => $batch,
                'datos_originales' => $row,
                'procesado' => false,
            ]));

            try {
                DB::transaction(function () use ($mapped, $stage, $batch, $index): void {
                    $subcategory = $this->resolveSubcategory($mapped['categoria'], $mapped['subcatg']);
                    $fechaAdquisicion = $mapped['fecha_adquisicion'] ?? now()->toDateString();
                    $fechaUltimaEntrada = $mapped['fecha_ultima_entrada'] ?? $fechaAdquisicion;
                    $fechaUltimaSalida = $mapped['fecha_ultima_salida'] ?? null;
                    $sku = $this->nullableString($mapped['sku']);

                    $attributes = [
                        'cod_ingreso' => 'IMP-' . $batch . '-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'descripcion' => (string) ($mapped['producto'] ?? ''),
                        'marca' => (string) ($mapped['marca'] ?? ''),
                        'subcategory_id' => $subcategory->id,
                        'serial' => $this->normalizeSerial($mapped['serial'] ?? null),
                        'estado' => (string) ($mapped['estado'] ?? ''),
                        'medida' => (string) ($mapped['medida'] ?? ''),
                        'ubicacion' => (string) ($mapped['ubicacion'] ?? ''),
                        'dpto_responsable' => (string) ($mapped['dpto_responsable'] ?? ''),
                        'stock_minimo' => (int) ($mapped['min'] ?? 0),
                        'stock_actual' => (int) ($mapped['cant_total'] ?? 0),
                        'precio_unitario' => (float) ($mapped['p_unitario'] ?? 0),
                        'fecha_adquisicion' => $fechaAdquisicion,
                        'fecha_ultima_entrada' => $fechaUltimaEntrada,
                        'fecha_ultima_salida' => $fechaUltimaSalida,
                        'is_archived' => Str::lower((string) ($mapped['estado_registro'] ?? '')) === 'archivado',
                    ];

                    $product = $sku !== null
                        ? Product::query()->where('sku', $sku)->first()
                        : null;

                    if ($product) {
                        $product->fill($attributes);
                        $product->save();
                    } else {
                        if ($sku !== null) {
                            $attributes['sku'] = $sku;
                        }

                        $product = Product::query()->create($attributes);
                    }

                    $stage->forceFill([
                        'product_id' => $product->id,
                        'procesado' => true,
                        'procesado_en' => now(),
                        'error_importacion' => null,
                    ])->save();
                });

                $processed++;
            } catch (\Throwable $throwable) {
                $stage->forceFill([
                    'error_importacion' => $throwable->getMessage(),
                ])->save();

                $errors[] = 'Fila ' . ($index + 2) . ': ' . $throwable->getMessage();
            }
        }

        return $this->buildResult($batch, $staged, $processed, $errors);
    }

    public function importEntradasFromStoredFile(string $absolutePath, ?int $userId = null, ?string $userName = null): array
    {
        $batch = $this->makeBatch('ENT');
        $rows = $this->readSpreadsheetRows($absolutePath);
        $staged = 0;
        $processed = 0;
        $errors = [];
        $groupedStages = [];

        foreach ($rows as $row) {
            $mapped = $this->mapEntradaRow($row);

            if ($this->rowIsEmpty($mapped, ['nro_control', 'sku', 'descripcion'])) {
                continue;
            }

            $staged++;

            $stage = InventarioEntradaImport::query()->create(array_merge($this->toEntradaStagePayload($mapped), [
                'lote_importacion' => $batch,
                'datos_originales' => $row,
                'procesado' => false,
            ]));

            $groupKey = (string) ($mapped['nro_control'] ?? 'SIN-CONTROL-' . $stage->id);
            $groupedStages[$groupKey][] = $stage->id;
        }

        foreach ($groupedStages as $groupKey => $stageIds) {
            $stages = InventarioEntradaImport::query()->whereIn('id', $stageIds)->get();

            try {
                DB::transaction(function () use ($stages, $groupKey, $userId, $userName): void {
                    if (InventoryMovement::query()->where('nro_control', $groupKey)->whereIn('tipo', ['ingreso', 'entrada'])->exists()) {
                        throw ValidationException::withMessages([
                            'nro_control' => 'Ya existe un movimiento de entrada/ingreso con el control ' . $groupKey . '.',
                        ]);
                    }

                    $movementType = $this->inferEntradaMovementType($stages);
                    $fecha = $this->resolveDateFromStages($stages, fn (InventarioEntradaImport $stage): ?string => $this->entradaStageData($stage)['fecha']);
                    $movementId = $this->insertInventoryMovement([
                        'tipo' => $movementType,
                        'fecha' => $fecha,
                        'nro_control' => $groupKey,
                        'orden_compra' => $this->firstResolvedValue($stages, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['orden_compra']),
                        'nro_solicitud' => $this->firstResolvedValue($stages, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['nro_solicitud']),
                        'factura_nota' => $this->firstResolvedValue($stages, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['factura_nota']),
                        'nro_doc_legal' => $this->firstResolvedValue($stages, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['nro_doc_legal']),
                        'proveedor' => $this->firstResolvedValue($stages, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['proveedor']),
                        'almacenista' => $userName,
                        'comentarios' => $this->firstResolvedValue($stages, fn (InventarioEntradaImport $stage): mixed => $this->entradaStageData($stage)['comentario']),
                        'created_by_user_id' => $userId,
                        'total_items' => $stages->count(),
                    ]);

                    foreach ($stages as $offset => $stage) {
                        $stageData = $this->entradaStageData($stage);
                        $cantidad = (int) ($stageData['cant'] ?? 0);
                        $precio = (float) ($stageData['precio'] ?? 0);

                        if ($cantidad <= 0) {
                            throw ValidationException::withMessages([
                                'cant' => 'La cantidad debe ser mayor a cero para el control ' . $groupKey . '.',
                            ]);
                        }

                        if ($movementType === 'ingreso') {
                            $subcategory = $this->resolveSubcategory($stageData['categoria'], $stageData['subcat']);
                            $product = Product::query()->create([
                                'sku' => $this->nullableString($stageData['sku']),
                                'cod_ingreso' => $groupKey . '-' . str_pad((string) ($offset + 1), 3, '0', STR_PAD_LEFT),
                                'descripcion' => (string) ($stageData['descripcion'] ?? ''),
                                'marca' => (string) ($stageData['marca'] ?? ''),
                                'subcategory_id' => $subcategory->id,
                                'serial' => $this->normalizeSerial($stageData['serial']),
                                'estado' => (string) ($stageData['estado'] ?? ''),
                                'medida' => (string) ($stageData['medida'] ?? ''),
                                'ubicacion' => (string) ($stageData['ubicacion'] ?? ''),
                                'dpto_responsable' => (string) ($stageData['dpto_responsible'] ?? ''),
                                'stock_minimo' => 0,
                                'stock_actual' => $cantidad,
                                'precio_unitario' => $precio,
                                'fecha_adquisicion' => $fecha,
                                'fecha_ultima_entrada' => $fecha,
                            ]);
                        } else {
                            $product = $this->findExistingProduct($stageData['sku'], $stageData['serial'], true);
                            $stockAnterior = (int) ($product->stock_actual ?? 0);
                            $precioAnterior = (float) ($product->precio_unitario ?? 0);
                            $stockNuevo = $stockAnterior + $cantidad;

                            $product->update([
                                'stock_actual' => $stockNuevo,
                                'precio_unitario' => $this->calculateWeightedAverageUnitPrice($stockAnterior, $precioAnterior, $cantidad, $precio),
                                'fecha_ultima_entrada' => $fecha,
                            ]);
                        }

                        $movementItem = MovementItem::query()->create([
                            'movement_id' => $movementId,
                            'product_id' => $product->id,
                            'cantidad' => $cantidad,
                            'precio_momento' => $precio,
                            'retorna' => false,
                            'observaciones_item' => $stageData['comentario'],
                        ]);

                        $stage->forceFill([
                            'inventory_movement_id' => $movementId,
                            'movement_item_id' => $movementItem->id,
                            'product_id' => $product->id,
                            'procesado' => true,
                            'procesado_en' => now(),
                            'error_importacion' => null,
                        ])->save();
                    }
                });

                $processed += count($stageIds);
            } catch (\Throwable $throwable) {
                InventarioEntradaImport::query()->whereIn('id', $stageIds)->update([
                    'error_importacion' => $throwable->getMessage(),
                ]);

                $errors[] = 'Control ' . $groupKey . ': ' . $throwable->getMessage();
            }
        }

        return $this->buildResult($batch, $staged, $processed, $errors);
    }

    public function importSalidasFromStoredFile(string $absolutePath, ?int $userId = null, ?string $userName = null): array
    {
        $batch = $this->makeBatch('SAL');
        $rows = $this->readSpreadsheetRows($absolutePath);
        $staged = 0;
        $processed = 0;
        $errors = [];
        $groupedStages = [];

        foreach ($rows as $row) {
            $mapped = $this->mapSalidaRow($row);

            if ($this->rowIsEmpty($mapped, ['nro_control', 'sku', 'descripcion'])) {
                continue;
            }

            $staged++;

            $stage = InventarioSalidaImport::query()->create(array_merge($this->toSalidaStagePayload($mapped), [
                'lote_importacion' => $batch,
                'datos_originales' => $row,
                'procesado' => false,
            ]));

            $groupKey = (string) ($mapped['nro_control'] ?? 'SIN-CONTROL-' . $stage->id);
            $groupedStages[$groupKey][] = $stage->id;
        }

        foreach ($groupedStages as $groupKey => $stageIds) {
            $stages = InventarioSalidaImport::query()->whereIn('id', $stageIds)->get();

            try {
                DB::transaction(function () use ($stages, $groupKey, $userId, $userName): void {
                    if (InventoryMovement::query()->where('nro_control', $groupKey)->where('tipo', 'salida')->exists()) {
                        throw ValidationException::withMessages([
                            'nro_control' => 'Ya existe una salida con el control ' . $groupKey . '.',
                        ]);
                    }

                    $fecha = $this->resolveDateFromStages($stages, fn (InventarioSalidaImport $stage): ?string => $this->salidaStageData($stage)['fecha']);
                    $movementId = $this->insertInventoryMovement([
                        'tipo' => 'salida',
                        'fecha' => $fecha,
                        'nro_control' => $groupKey,
                        'almacenista' => $this->firstResolvedValue($stages, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['quien_entrega']) ?? $userName,
                        'responsable_destino' => $this->firstResolvedValue($stages, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['responsable']),
                        'dpto_destino' => $this->firstResolvedValue($stages, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['area_dpto']),
                        'comentarios' => $this->firstResolvedValue($stages, fn (InventarioSalidaImport $stage): mixed => $this->salidaStageData($stage)['observaciones']),
                        'created_by_user_id' => $userId,
                        'total_items' => $stages->count(),
                    ]);

                    foreach ($stages as $stage) {
                        $stageData = $this->salidaStageData($stage);
                        $product = $this->findExistingProduct($stageData['sku'], $stageData['serial'], true, true);
                        $cantidad = (int) ($stageData['cant'] ?? 0);

                        if ($cantidad <= 0) {
                            throw ValidationException::withMessages([
                                'cant' => 'La cantidad debe ser mayor a cero para el control ' . $groupKey . '.',
                            ]);
                        }

                        if ($cantidad > (int) $product->stock_actual) {
                            throw ValidationException::withMessages([
                                'cant' => 'No hay stock suficiente para el SKU ' . $product->sku . ' en el control ' . $groupKey . '.',
                            ]);
                        }

                        $precioMomento = (float) ($product->precio_unitario ?? 0);

                        $product->update([
                            'stock_actual' => (int) $product->stock_actual - $cantidad,
                            'fecha_ultima_salida' => $fecha,
                        ]);

                        $movementItem = MovementItem::query()->create([
                            'movement_id' => $movementId,
                            'product_id' => $product->id,
                            'cantidad' => $cantidad,
                            'precio_momento' => $precioMomento,
                            'retorna' => $this->parseRetornaFlag($stageData['retorna']),
                            'observaciones_item' => $stageData['observaciones'],
                        ]);

                        $stage->forceFill([
                            'inventory_movement_id' => $movementId,
                            'movement_item_id' => $movementItem->id,
                            'product_id' => $product->id,
                            'procesado' => true,
                            'procesado_en' => now(),
                            'error_importacion' => null,
                        ])->save();
                    }
                });

                $processed += count($stageIds);
            } catch (\Throwable $throwable) {
                InventarioSalidaImport::query()->whereIn('id', $stageIds)->update([
                    'error_importacion' => $throwable->getMessage(),
                ]);

                $errors[] = 'Control ' . $groupKey . ': ' . $throwable->getMessage();
            }
        }

        return $this->buildResult($batch, $staged, $processed, $errors);
    }

    private function readSpreadsheetRows(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($this->resolveImportPath($absolutePath));
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);
        $headers = array_map(fn ($value): string => trim((string) $value), array_shift($rawRows) ?? []);

        $rows = [];

        foreach ($rawRows as $rawRow) {
            $assoc = [];

            foreach ($headers as $index => $header) {
                $assoc[$header] = $rawRow[$index] ?? null;
            }

            $rows[] = $assoc;
        }

        return $rows;
    }

    private function resolveImportPath(string $path): string
    {
        $trimmed = trim($path, " \t\n\r\0\x0B\"");

        if ($trimmed === '') {
            throw new \RuntimeException('No se recibio una ruta de archivo valida para importar.');
        }

        if (is_file($trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^[A-Za-z]:[\\\/]/', $trimmed) === 1) {
            throw new \RuntimeException('El archivo importado no existe en la ruta indicada: ' . $trimmed);
        }

        $diskPath = Storage::disk('local')->path($trimmed);

        if (is_file($diskPath)) {
            return $diskPath;
        }

        $storagePath = storage_path('app/' . ltrim(str_replace('\\', '/', $trimmed), '/'));

        if (is_file($storagePath)) {
            return $storagePath;
        }

        throw new \RuntimeException('El archivo importado no existe en storage: ' . $trimmed);
    }

    private function stageAttribute(object $stage, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = null;

            if ($stage instanceof Model) {
                $attributes = $stage->getAttributes();

                if (array_key_exists($key, $attributes)) {
                    $value = $stage->getRawOriginal($key);
                }
            }

            if ($value === null && method_exists($stage, 'getAttribute')) {
                $value = $stage->getAttribute($key);
            }

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function mapProductRow(array $row): array
    {
        return [
            'sku' => $this->mapValue($row, ['sku']),
            'producto' => $this->mapValue($row, ['producto', 'descripcion']),
            'marca' => $this->mapValue($row, ['marca']),
            'categoria' => $this->mapValue($row, ['categoria']),
            'subcatg' => $this->mapValue($row, ['subcatg', 'subcat', 'subcategoria']),
            'estado' => $this->mapValue($row, ['estado']),
            'medida' => $this->mapValue($row, ['medida']),
            'serial' => $this->mapValue($row, ['serial']),
            'almacen' => $this->mapValue($row, ['almacen']),
            'ubicacion' => $this->mapValue($row, ['ubicacion']),
            'dpto_responsable' => $this->mapValue($row, ['dptoresponsable', 'dptoresponsible']),
            'min' => $this->toInteger($this->mapValue($row, ['min'])),
            'status' => $this->toInteger($this->mapValue($row, ['status'])),
            'cant_total' => $this->toInteger($this->mapValue($row, ['canttotal', 'cantidadtotal'])),
            'entradas' => $this->toInteger($this->mapValue($row, ['entradas'])),
            'salidas' => $this->toInteger($this->mapValue($row, ['salidas'])),
            'p_unitario' => $this->toDecimal($this->mapValue($row, ['punitario', 'preciounitario'])),
            'p_total' => $this->toDecimal($this->mapValue($row, ['ptotal', 'preciototal'])),
            'fecha_adquisicion' => $this->toDateString($this->mapValue($row, ['fechadeadquisicion'])),
            'fecha_ultima_entrada' => $this->toDateString($this->mapValue($row, ['fechadeultimaentrada'])),
            'fecha_ultima_salida' => $this->toDateString($this->mapValue($row, ['fechadeultimasalida'])),
            'estado_registro' => $this->mapValue($row, ['estadoregistro', 'estado2']),
        ];
    }

    private function mapEntradaRow(array $row): array
    {
        return [
            'nro_control' => $this->mapValue($row, ['ncontrol']),
            'fecha' => $this->toDateString($this->mapValue($row, ['fecha'])),
            'mes' => $this->mapValue($row, ['mes']),
            'nro_solicitud' => $this->mapValue($row, ['ndesolicitud']),
            'orden_compra' => $this->mapValue($row, ['ordendecompra']),
            'factura_nota' => $this->mapValue($row, ['fni']),
            'nro_doc_legal' => $this->mapValue($row, ['n']),
            'proveedor' => $this->mapValue($row, ['proveedor']),
            'sku' => $this->mapValue($row, ['sku']),
            'descripcion' => $this->mapValue($row, ['descripcion']),
            'marca' => $this->mapValue($row, ['marca']),
            'categoria' => $this->mapValue($row, ['categoria']),
            'subcat' => $this->mapValue($row, ['subcat']),
            'serial' => $this->mapValue($row, ['serial']),
            'estado' => $this->mapValue($row, ['estado']),
            'medida' => $this->mapValue($row, ['medida']),
            'cant' => $this->toInteger($this->mapValue($row, ['cant'])),
            'ubicacion' => $this->mapValue($row, ['ubicacion']),
            'dpto_responsible' => $this->mapValue($row, ['dptoresponsible', 'dptoresponsable']),
            'precio' => $this->toDecimal($this->mapValue($row, ['precio'])),
            'comentario' => $this->mapValue($row, ['comentario']),
        ];
    }

    private function mapSalidaRow(array $row): array
    {
        return [
            'nro_control' => $this->mapValue($row, ['ncontrol']),
            'fecha' => $this->toDateString($this->mapValue($row, ['fecha'])),
            'mes' => $this->mapValue($row, ['mes']),
            'responsable' => $this->mapValue($row, ['responsable']),
            'area_dpto' => $this->mapValue($row, ['areadpto']),
            'quien_entrega' => $this->mapValue($row, ['quienentrega']),
            'sku' => $this->mapValue($row, ['sku']),
            'descripcion' => $this->mapValue($row, ['descripcion']),
            'marca' => $this->mapValue($row, ['marca']),
            'categoria' => $this->mapValue($row, ['categoria']),
            'subcat' => $this->mapValue($row, ['subcat']),
            'serial' => $this->mapValue($row, ['serial']),
            'estado' => $this->mapValue($row, ['estado']),
            'medida' => $this->mapValue($row, ['medida']),
            'cant' => $this->toInteger($this->mapValue($row, ['cant'])),
            'ubicacion' => $this->mapValue($row, ['ubicacion']),
            'retorna' => $this->mapValue($row, ['retorna']),
            'observaciones' => $this->mapValue($row, ['observaciones']),
        ];
    }

    private function toProductStagePayload(array $mapped): array
    {
        return [
            'SKU' => $mapped['sku'] ?? null,
            'PRODUCTO' => $mapped['producto'] ?? null,
            'MARCA' => $mapped['marca'] ?? null,
            'CATEGORIA' => $mapped['categoria'] ?? null,
            'SUBCATG' => $mapped['subcatg'] ?? null,
            'ESTADO' => $mapped['estado'] ?? null,
            'MEDIDA' => $mapped['medida'] ?? null,
            'SERIAL' => $mapped['serial'] ?? null,
            'ALMACEN' => $mapped['almacen'] ?? null,
            'UBICACION' => $mapped['ubicacion'] ?? null,
            'RESPONSABLE' => $mapped['dpto_responsable'] ?? null,
            'MIN' => $mapped['min'] ?? null,
            'STATUS (1,2,3)' => $mapped['status'] ?? null,
            'CANT_TOTAL' => $mapped['cant_total'] ?? null,
            'ENTRADAS' => $mapped['entradas'] ?? null,
            'SALIDAS' => $mapped['salidas'] ?? null,
            'P_UNITARIO' => $mapped['p_unitario'] ?? null,
            'P_TOTAL' => $mapped['p_total'] ?? null,
            'FECHA DE ADQUISICION' => $mapped['fecha_adquisicion'] ?? null,
            'FECHA DE ULTIMA ENTRADA' => $mapped['fecha_ultima_entrada'] ?? null,
            'FECHA DE ULTIMA SALIDA' => $mapped['fecha_ultima_salida'] ?? null,
            'ESTADO REGISTRO' => $mapped['estado_registro'] ?? null,
        ];
    }

    private function toEntradaStagePayload(array $mapped): array
    {
        return [
            'N° CONTROL' => $mapped['nro_control'] ?? null,
            'FECHA' => $mapped['fecha'] ?? null,
            'MES' => $mapped['mes'] ?? null,
            'N° DE SOLICITUD' => $mapped['nro_solicitud'] ?? null,
            'ORDEN DE COMPRA' => $mapped['orden_compra'] ?? null,
            'F/N/I' => $mapped['factura_nota'] ?? null,
            'N°' => $mapped['nro_doc_legal'] ?? null,
            'PROVEEDOR' => $mapped['proveedor'] ?? null,
            'SKU' => $mapped['sku'] ?? null,
            'DESCRIPCION' => $mapped['descripcion'] ?? null,
            'MARCA' => $mapped['marca'] ?? null,
            'CATEGORIA' => $mapped['categoria'] ?? null,
            'SUBCAT' => $mapped['subcat'] ?? null,
            'SERIAL' => $mapped['serial'] ?? null,
            'ESTADO' => $mapped['estado'] ?? null,
            'MEDIDA' => $mapped['medida'] ?? null,
            'CANT' => $mapped['cant'] ?? null,
            'UBICACION' => $mapped['ubicacion'] ?? null,
            'DPTO RESPONSIBLE' => $mapped['dpto_responsible'] ?? null,
            'PRECIO' => $mapped['precio'] ?? null,
            'COMENTARIO' => $mapped['comentario'] ?? null,
        ];
    }

    private function toSalidaStagePayload(array $mapped): array
    {
        return [
            'N° CONTROL' => $mapped['nro_control'] ?? null,
            'FECHA' => $mapped['fecha'] ?? null,
            'MES' => $mapped['mes'] ?? null,
            'RESPONSABLE' => $mapped['responsable'] ?? null,
            'AREA/DPTO' => $mapped['area_dpto'] ?? null,
            'QUIEN ENTREGA' => $mapped['quien_entrega'] ?? null,
            'SKU' => $mapped['sku'] ?? null,
            'DESCRIPCION' => $mapped['descripcion'] ?? null,
            'MARCA' => $mapped['marca'] ?? null,
            'CATEGORIA' => $mapped['categoria'] ?? null,
            'SUBCAT' => $mapped['subcat'] ?? null,
            'SERIAL' => $mapped['serial'] ?? null,
            'ESTADO' => $mapped['estado'] ?? null,
            'MEDIDA' => $mapped['medida'] ?? null,
            'CANT' => $mapped['cant'] ?? null,
            'UBICACION' => $mapped['ubicacion'] ?? null,
            'RETORNA' => $mapped['retorna'] ?? null,
            'OBSERVACIONES' => $mapped['observaciones'] ?? null,
        ];
    }

    private function inferEntradaMovementType(Collection $stages): string
    {
        $existing = 0;
        $missing = 0;

        foreach ($stages as $stage) {
            $stageData = $this->entradaStageData($stage);
            $product = $this->findExistingProduct($stageData['sku'], $stageData['serial'], false);

            if ($product) {
                $existing++;
            } else {
                $missing++;
            }
        }

        if ($existing > 0 && $missing > 0) {
            throw ValidationException::withMessages([
                'sku' => 'El control contiene mezcla de productos existentes y nuevos. Importa ese control por separado.',
            ]);
        }

        return $missing > 0 ? 'ingreso' : 'entrada';
    }

    private function insertInventoryMovement(array $attributes): int
    {
        return (int) DB::table('inventory_movements')->insertGetId([
            'tipo' => $attributes['tipo'],
            'fecha' => $attributes['fecha'],
            'nro_control' => $attributes['nro_control'],
            'created_by_user_id' => $attributes['created_by_user_id'] ?? null,
            'updated_by_user_id' => null,
            'orden_compra' => $attributes['orden_compra'] ?? null,
            'nro_solicitud' => $attributes['nro_solicitud'] ?? null,
            'factura_nota' => $attributes['factura_nota'] ?? null,
            'nro_doc_legal' => $attributes['nro_doc_legal'] ?? null,
            'proveedor' => $attributes['proveedor'] ?? null,
            'almacenista' => $attributes['almacenista'] ?? null,
            'responsable_destino' => $attributes['responsable_destino'] ?? null,
            'dpto_destino' => $attributes['dpto_destino'] ?? null,
            'comentarios' => $attributes['comentarios'] ?? null,
            'solicitar_formato_entrada' => false,
            'total_items' => (int) ($attributes['total_items'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function findExistingProduct(mixed $sku, mixed $serial, bool $failIfMissing, bool $lockForUpdate = false): ?Product
    {
        $sku = $this->nullableString($sku);
        $serial = $this->normalizeLookupSerial($serial);

        $query = Product::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $product = null;

        if ($sku !== null) {
            $product = (clone $query)->where('sku', $sku)->first();
        }

        if (! $product && $serial !== null) {
            $product = (clone $query)->where('serial', $serial)->first();
        }

        if (! $product && $failIfMissing) {
            throw ValidationException::withMessages([
                'sku' => 'No se encontro el producto para SKU/serial importado.',
            ]);
        }

        return $product;
    }

    private function resolveSubcategory(?string $categoryName, ?string $subcategoryName): Subcategory
    {
        $categoryName = $this->nullableString($categoryName);
        $subcategoryName = $this->nullableString($subcategoryName);

        if ($categoryName === null || $subcategoryName === null) {
            throw ValidationException::withMessages([
                'categoria' => 'Categoria y subcategoria son obligatorias para importar productos nuevos.',
            ]);
        }

        $category = Category::query()->firstOrCreate([
            'name' => $categoryName,
        ]);

        return Subcategory::query()->firstOrCreate([
            'category_id' => $category->id,
            'name' => $subcategoryName,
        ]);
    }

    private function calculateWeightedAverageUnitPrice(int $currentStock, float $currentUnitPrice, int $incomingQty, float $incomingUnitPrice): float
    {
        $newStock = $currentStock + $incomingQty;

        if ($newStock <= 0) {
            return round(max(0, $currentUnitPrice), 2);
        }

        $currentValue = $currentStock * $currentUnitPrice;
        $incomingValue = $incomingQty * $incomingUnitPrice;

        return round(max(0, ($currentValue + $incomingValue) / $newStock), 2);
    }

    private function mapValue(array $row, array $keys): mixed
    {
        $normalized = [];

        foreach ($row as $header => $value) {
            $normalized[$this->normalizeHeader((string) $header)] = $value;
        }

        foreach ($keys as $key) {
            $lookup = $this->normalizeHeader($key);

            if (array_key_exists($lookup, $normalized)) {
                return $normalized[$lookup];
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::ascii($value);
        $value = Str::lower($value);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function toInteger(mixed $value): ?int
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        return (int) preg_replace('/[^0-9\-]/', '', $value);
    }

    private function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = str_replace([' ', '.'], ['', ''], (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    private function toDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    private function parseRetornaFlag(mixed $value): bool
    {
        $value = Str::upper(trim((string) $value));

        return in_array($value, ['SI', 'S', '1', 'TRUE', 'YES'], true);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeSerial(mixed $value): string
    {
        $serial = $this->normalizeLookupSerial($value);

        return $serial ?? 'SIN SERIAL';
    }

    private function normalizeLookupSerial(mixed $value): ?string
    {
        $serial = $this->nullableString($value);

        if ($serial === null) {
            return null;
        }

        $normalized = Str::upper(Str::ascii($serial));
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';

        if ($normalized === '' || in_array($normalized, ['NA', 'NAS', 'N/A', 'SINSERIAL', 'SN', 'NONE', 'NULL'], true)) {
            return null;
        }

        return $serial;
    }

    private function latestPendingBatch(string $modelClass): ?string
    {
        $batch = $modelClass::query()
            ->where('procesado', false)
            ->whereNotNull('lote_importacion')
            ->orderByDesc('id')
            ->value('lote_importacion');

        return $this->nullableString($batch);
    }

    private function firstNonEmptyFromStages(Collection $stages, string $field): ?string
    {
        return $stages
            ->pluck($field)
            ->map(fn ($value): ?string => $this->nullableString($value))
            ->first(fn (?string $value): bool => $value !== null);
    }

    private function firstResolvedValue(Collection $stages, callable $resolver): ?string
    {
        foreach ($stages as $stage) {
            $value = $this->nullableString($resolver($stage));

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function resolveDateFromStages(Collection $stages, callable $resolver): string
    {
        foreach ($stages as $stage) {
            $value = $resolver($stage);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return now()->toDateString();
    }

    private function productStageData(AlmacenAdvImport $stage): array
    {
        return [
            'sku' => $this->nullableString($this->stageAttribute($stage, ['SKU', 'sku'])),
            'producto' => $this->nullableString($this->stageAttribute($stage, ['PRODUCTO', 'producto'])),
            'marca' => $this->nullableString($this->stageAttribute($stage, ['MARCA', 'marca'])),
            'categoria' => $this->nullableString($this->stageAttribute($stage, ['CATEGORIA', 'categoria'])),
            'subcatg' => $this->nullableString($this->stageAttribute($stage, ['SUBCATG', 'subcatg'])),
            'estado' => $this->nullableString($this->stageAttribute($stage, ['ESTADO', 'estado'])),
            'medida' => $this->nullableString($this->stageAttribute($stage, ['MEDIDA', 'medida'])),
            'serial' => $this->nullableString($this->stageAttribute($stage, ['SERIAL', 'serial'])),
            'almacen' => $this->nullableString($this->stageAttribute($stage, ['ALMACEN', 'ALMACEN ', 'almacen'])),
            'ubicacion' => $this->nullableString($this->stageAttribute($stage, ['UBICACION', 'ubicacion'])),
            'dpto_responsable' => $this->nullableString($this->stageAttribute($stage, ['RESPONSABLE', 'dpto_responsable', 'responsable'])),
            'min' => $this->toInteger($this->stageAttribute($stage, ['MIN', 'min'])),
            'status' => $this->toInteger($this->stageAttribute($stage, ['STATUS (1,2,3)', 'STATUS (1,2.3)', 'status'])),
            'cant_total' => $this->toInteger($this->stageAttribute($stage, ['CANT_TOTAL', 'CANT. TOTAL', 'cant_total'])),
            'entradas' => $this->toInteger($this->stageAttribute($stage, ['ENTRADAS', 'entradas'])),
            'salidas' => $this->toInteger($this->stageAttribute($stage, ['SALIDAS', 'salidas'])),
            'p_unitario' => $this->toDecimal($this->stageAttribute($stage, ['P_UNITARIO', 'P.UNITARIO', 'p_unitario'])),
            'p_total' => $this->toDecimal($this->stageAttribute($stage, ['P_TOTAL', 'P.TOTAL', 'p_total'])),
            'fecha_adquisicion' => $this->toDateString($this->stageAttribute($stage, ['FECHA DE ADQUISICION', 'fecha_adquisicion'])),
            'fecha_ultima_entrada' => $this->toDateString($this->stageAttribute($stage, ['FECHA DE ULTIMA ENTRADA', 'fecha_ultima_entrada'])),
            'fecha_ultima_salida' => $this->toDateString($this->stageAttribute($stage, ['FECHA DE ULTIMA SALIDA', 'fecha_ultima_salida'])),
            'estado_registro' => $this->nullableString($this->stageAttribute($stage, ['ESTADO REGISTRO', 'estado_registro'])),
        ];
    }

    private function entradaStageData(InventarioEntradaImport $stage): array
    {
        return [
            'nro_control' => $this->nullableString($this->stageAttribute($stage, ['N° CONTROL', 'nro_control'])),
            'fecha' => $this->toDateString($this->stageAttribute($stage, ['FECHA', 'fecha'])),
            'mes' => $this->nullableString($this->stageAttribute($stage, ['MES', 'mes'])),
            'nro_solicitud' => $this->nullableString($this->stageAttribute($stage, ['N° DE SOLICITUD', 'N°  DE SOLICITUD', 'nro_solicitud'])),
            'orden_compra' => $this->nullableString($this->stageAttribute($stage, ['ORDEN DE COMPRA', 'orden_compra'])),
            'factura_nota' => $this->nullableString($this->stageAttribute($stage, ['F/N/I', 'factura_nota'])),
            'nro_doc_legal' => $this->nullableString($this->stageAttribute($stage, ['N°', 'nro_doc_legal'])),
            'proveedor' => $this->nullableString($this->stageAttribute($stage, ['PROVEEDOR', 'proveedor'])),
            'sku' => $this->nullableString($this->stageAttribute($stage, ['SKU', 'sku'])),
            'descripcion' => $this->nullableString($this->stageAttribute($stage, ['DESCRIPCION', 'descripcion'])),
            'marca' => $this->nullableString($this->stageAttribute($stage, ['MARCA', 'marca'])),
            'categoria' => $this->nullableString($this->stageAttribute($stage, ['CATEGORIA', 'categoria'])),
            'subcat' => $this->nullableString($this->stageAttribute($stage, ['SUBCAT', 'subcat'])),
            'serial' => $this->nullableString($this->stageAttribute($stage, ['SERIAL', 'serial'])),
            'estado' => $this->nullableString($this->stageAttribute($stage, ['ESTADO', 'estado'])),
            'medida' => $this->nullableString($this->stageAttribute($stage, ['MEDIDA', 'medida'])),
            'cant' => $this->toInteger($this->stageAttribute($stage, ['CANT', 'cant'])),
            'ubicacion' => $this->nullableString($this->stageAttribute($stage, ['UBICACION', 'ubicacion'])),
            'dpto_responsible' => $this->nullableString($this->stageAttribute($stage, ['DPTO RESPONSIBLE', 'dpto_responsible'])),
            'precio' => $this->toDecimal($this->stageAttribute($stage, ['PRECIO', 'precio'])),
            'comentario' => $this->nullableString($this->stageAttribute($stage, ['COMENTARIO', 'comentario'])),
        ];
    }

    private function salidaStageData(InventarioSalidaImport $stage): array
    {
        return [
            'nro_control' => $this->nullableString($this->stageAttribute($stage, ['N° CONTROL', 'nro_control'])),
            'fecha' => $this->toDateString($this->stageAttribute($stage, ['FECHA', 'fecha'])),
            'mes' => $this->nullableString($this->stageAttribute($stage, ['MES', 'mes'])),
            'responsable' => $this->nullableString($this->stageAttribute($stage, ['RESPONSABLE', 'responsable'])),
            'area_dpto' => $this->nullableString($this->stageAttribute($stage, ['AREA/DPTO', 'area_dpto'])),
            'quien_entrega' => $this->nullableString($this->stageAttribute($stage, ['QUIEN ENTREGA', 'quien_entrega'])),
            'sku' => $this->nullableString($this->stageAttribute($stage, ['SKU', 'sku'])),
            'descripcion' => $this->nullableString($this->stageAttribute($stage, ['DESCRIPCION', 'descripcion'])),
            'marca' => $this->nullableString($this->stageAttribute($stage, ['MARCA', 'marca'])),
            'categoria' => $this->nullableString($this->stageAttribute($stage, ['CATEGORIA', 'categoria'])),
            'subcat' => $this->nullableString($this->stageAttribute($stage, ['SUBCAT', 'subcat'])),
            'serial' => $this->nullableString($this->stageAttribute($stage, ['SERIAL', 'serial'])),
            'estado' => $this->nullableString($this->stageAttribute($stage, ['ESTADO', 'estado'])),
            'medida' => $this->nullableString($this->stageAttribute($stage, ['MEDIDA', 'medida'])),
            'cant' => $this->toInteger($this->stageAttribute($stage, ['CANT', 'cant'])),
            'ubicacion' => $this->nullableString($this->stageAttribute($stage, ['UBICACION', 'ubicacion'])),
            'retorna' => $this->nullableString($this->stageAttribute($stage, ['RETORNA', 'retorna'])),
            'observaciones' => $this->nullableString($this->stageAttribute($stage, ['OBSERVACIONES', 'observaciones'])),
        ];
    }

    private function resolveGroupDate(Collection $stages, string $field): string
    {
        return $this->firstNonEmptyFromStages($stages, $field) ?? now()->toDateString();
    }

    private function rowIsEmpty(array $mapped, array $keys): bool
    {
        foreach ($keys as $key) {
            $value = Arr::get($mapped, $key);

            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function buildResult(string $batch, int $staged, int $processed, array $errors): array
    {
        return [
            'batch' => $batch,
            'staged' => $staged,
            'processed' => $processed,
            'failed' => count($errors),
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    private function makeBatch(string $prefix): string
    {
        return $prefix . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
    }
}