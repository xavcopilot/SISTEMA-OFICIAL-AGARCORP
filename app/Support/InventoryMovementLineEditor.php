<?php

namespace App\Support;

use App\Models\InventoryMovement;
use App\Models\MovementItem;
use App\Models\Product;
use App\Models\Subcategory;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryMovementLineEditor
{
    private const MAX_ENTRADA_ITEMS = 12;
    private const MAX_SALIDA_ITEMS = 13;

    public const REMOVAL_REASONS = [
        'falta_informacion' => 'Falta de información',
        'error_escritura' => 'Error de escritura',
        'material_no_existente' => 'Material no existente',
        'material_danado' => 'Material dañado',
        'registro_duplicado' => 'Registro duplicado',
        'otro_controlado' => 'Otro controlado',
    ];

    public static function removalReasonOptions(): array
    {
        return self::REMOVAL_REASONS;
    }

    public static function updateEntrada(InventoryMovement $movement, array $movementData, array $itemsData): void
    {
        if ((string) $movement->tipo !== 'entrada') {
            throw ValidationException::withMessages([
                'items' => 'Solo se permiten ajustes de lineas para movimientos tipo entrada.',
            ]);
        }

        self::apply($movement, $movementData, $itemsData, +1, self::MAX_ENTRADA_ITEMS, 'entrada');
    }

    public static function updateIngreso(InventoryMovement $movement, array $movementData, array $itemsData): void
    {
        if ((string) $movement->tipo !== 'ingreso') {
            throw ValidationException::withMessages([
                'items' => 'Solo se permiten ajustes de lineas para movimientos tipo ingreso.',
            ]);
        }

        self::applyIngresoDetailed($movement, $movementData, $itemsData);
    }

    public static function updateSalida(InventoryMovement $movement, array $movementData, array $itemsData): void
    {
        if ((string) $movement->tipo !== 'salida') {
            throw ValidationException::withMessages([
                'items' => 'Solo se permiten ajustes de lineas para movimientos tipo salida.',
            ]);
        }

        self::apply($movement, $movementData, $itemsData, -1, self::MAX_SALIDA_ITEMS, 'salida');
    }

    private static function apply(
        InventoryMovement $movement,
        array $movementData,
        array $itemsData,
        int $movementSign,
        int $maxItems,
        string $tipo
    ): void
    {
        $criticalSkus = [];

        DB::transaction(function () use ($movement, $movementData, $itemsData, $movementSign, &$criticalSkus): void {
            $movement->loadMissing('items.product');

            $normalizedRows = self::normalizeRows($itemsData, $movementSign);
            $removedByItemId = self::extractRemovedItems($normalizedRows);
            $finalRows = array_values(array_filter($normalizedRows, fn (array $row): bool => ! $row['eliminar_linea']));

            if ($finalRows === []) {
                throw ValidationException::withMessages([
                    'items' => 'Debe quedar al menos una linea activa en el movimiento.',
                ]);
            }

            if (count($finalRows) > $maxItems) {
                throw ValidationException::withMessages([
                    'items' => 'Solo se permiten ' . $maxItems . ' articulos por ' . $tipo . '.',
                ]);
            }

            self::assertNoDuplicateSkuLines($finalRows);

            $originalItems = MovementItem::query()
                ->where('movement_id', $movement->id)
                ->get()
                ->keyBy('id');

            foreach ($removedByItemId as $itemId => $reason) {
                if (! $originalItems->has($itemId)) {
                    throw ValidationException::withMessages([
                        'items' => 'Una linea marcada para eliminar no existe en el movimiento.',
                    ]);
                }

                if ($reason === null || ! array_key_exists($reason, self::REMOVAL_REASONS)) {
                    throw ValidationException::withMessages([
                        'items' => 'Selecciona un motivo de eliminacion valido para cada linea eliminada.',
                    ]);
                }
            }

            $originalByProduct = self::sumByProduct($originalItems->all());
            $originalValueByProduct = self::sumValueByProduct($originalItems->all());
            $finalByProduct = self::sumRowsByProduct($finalRows);
            $finalValueByProduct = self::sumRowsValueByProduct($finalRows);

            $allProductIds = array_values(array_unique(array_merge(array_keys($originalByProduct), array_keys($finalByProduct))));
            $products = Product::query()
                ->whereIn('id', $allProductIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($allProductIds as $productId) {
                $product = $products->get($productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos seleccionados no existe.',
                    ]);
                }

                $originalQty = $originalByProduct[$productId] ?? 0;
                $finalQty = $finalByProduct[$productId] ?? 0;
                $effectDelta = ($finalQty - $originalQty) * $movementSign;
                $newStock = (int) $product->stock_actual + $effectDelta;

                if ($newStock < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'El ajuste deja stock negativo para el SKU ' . $product->sku . '.',
                    ]);
                }

                if ($newStock < (int) $product->stock_minimo) {
                    $criticalSkus[] = (string) $product->sku;
                }

                $product->stock_actual = $newStock;

                if ($movementSign > 0) {
                    $currentStock = (int) ($product->stock_actual - $effectDelta);
                    $currentValue = ((float) ($product->precio_unitario ?? 0)) * $currentStock;

                    $originalValue = (float) ($originalValueByProduct[$productId] ?? 0);
                    $finalValue = (float) ($finalValueByProduct[$productId] ?? 0);

                    $baseValue = $currentValue - $originalValue;
                    $newValue = $baseValue + $finalValue;

                    $product->precio_unitario = $newStock > 0
                        ? round(max(0, $newValue / $newStock), 2)
                        : 0;

                    $product->fecha_ultima_entrada = now()->toDateString();
                } else {
                    $product->fecha_ultima_salida = now()->toDateString();
                }

                $product->save();
            }

            $movement->update(array_merge($movementData, [
                'total_items' => count($finalRows),
            ]));

            foreach ($removedByItemId as $itemId => $reason) {
                $original = $originalItems->get($itemId);
                DB::table('movement_item_removal_logs')->insert([
                    'movement_id' => $movement->id,
                    'movement_item_id' => $original?->id,
                    'product_id' => $original?->product_id,
                    'sku_snapshot' => (string) ($original?->product?->sku ?? ''),
                    'cantidad' => (int) ($original?->cantidad ?? 0),
                    'motivo' => (string) $reason,
                    'removed_by_user_id' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            MovementItem::query()->where('movement_id', $movement->id)->delete();

            foreach ($finalRows as $row) {
                MovementItem::create([
                    'movement_id' => $movement->id,
                    'product_id' => $row['product_id'],
                    'cantidad' => $row['cantidad'],
                    'precio_momento' => $row['precio_momento'],
                    'retorna' => $row['retorna'],
                    'observaciones_item' => $row['observaciones_item'],
                ]);
            }
        });

        self::notifyCriticalProducts($criticalSkus, 'ajuste de ' . (string) $movement->tipo);
    }

    private static function applyIngresoDetailed(InventoryMovement $movement, array $movementData, array $itemsData): void
    {
        $criticalSkus = [];

        DB::transaction(function () use ($movement, $movementData, $itemsData, &$criticalSkus): void {
            $movement->loadMissing('items.product.subcategory');

            $normalizedRows = self::normalizeIngresoRows($itemsData);
            $removedByItemId = self::extractRemovedItems($normalizedRows);
            $finalRows = array_values(array_filter($normalizedRows, fn (array $row): bool => ! $row['eliminar_linea']));

            if ($finalRows === []) {
                throw ValidationException::withMessages([
                    'items' => 'Debe quedar al menos una linea activa en el movimiento.',
                ]);
            }

            $originalItems = MovementItem::query()
                ->where('movement_id', $movement->id)
                ->get()
                ->keyBy('id');

            foreach ($removedByItemId as $itemId => $reason) {
                if (! $originalItems->has($itemId)) {
                    throw ValidationException::withMessages([
                        'items' => 'Una linea marcada para eliminar no existe en el movimiento.',
                    ]);
                }

                if ($reason === null || ! array_key_exists($reason, self::REMOVAL_REASONS)) {
                    throw ValidationException::withMessages([
                        'items' => 'Selecciona un motivo de eliminacion valido para cada linea eliminada.',
                    ]);
                }
            }

            $originalByProduct = self::sumByProduct($originalItems->all());
            $touchedProductIds = collect($finalRows)
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->merge(array_keys($originalByProduct))
                ->unique()
                ->values()
                ->all();

            $lockedProducts = Product::query()
                ->whereIn('id', $touchedProductIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $resolvedRows = [];

            foreach ($finalRows as $index => $row) {
                $quantity = (int) ($row['cantidad'] ?? 0);
                $price = (float) ($row['precio_momento'] ?? 0);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'La cantidad debe ser mayor a cero en todas las lineas activas.',
                    ]);
                }

                if ($price < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'El precio no puede ser negativo.',
                    ]);
                }

                $subcategoryId = (int) ($row['subcategory_id'] ?? 0);

                if ($subcategoryId <= 0 || ! Subcategory::query()->whereKey($subcategoryId)->exists()) {
                    throw ValidationException::withMessages([
                        'items' => 'Cada linea activa debe tener una subcategoria valida.',
                    ]);
                }

                $productId = (int) ($row['product_id'] ?? 0);
                $product = $productId > 0 ? $lockedProducts->get($productId) : null;

                if (! $product && $productId > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos de la edicion no existe.',
                    ]);
                }

                if (! $product) {
                    $product = new Product();
                    $product->cod_ingreso = (string) $movement->nro_control . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                    $product->fecha_adquisicion = now()->toDateString();
                }

                $inputSku = trim((string) ($row['sku'] ?? ''));

                if ($inputSku !== '') {
                    $product->sku = Str::upper($inputSku);
                } elseif (! $product->exists) {
                    $product->sku = null;
                }

                $product->descripcion = trim((string) ($row['descripcion'] ?? ''));
                $product->marca = trim((string) ($row['marca'] ?? ''));
                $product->subcategory_id = $subcategoryId;
                $product->serial = trim((string) ($row['serial'] ?? ''));
                $product->estado = trim((string) ($row['estado'] ?? ''));
                $product->medida = trim((string) ($row['medida'] ?? ''));
                $product->ubicacion = trim((string) ($row['ubicacion'] ?? ''));
                $product->dpto_responsable = trim((string) ($row['dpto_responsable'] ?? ''));
                $product->stock_minimo = max(0, (int) ($row['stock_minimo'] ?? 0));
                if (! $product->exists) {
                    $product->precio_unitario = $price;
                    $product->stock_actual = (int) ($product->stock_actual ?? 0);
                    $product->fecha_ultima_entrada = now()->toDateString();
                }

                $product->save();

                $resolvedRows[] = [
                    'movement_item_id' => $row['movement_item_id'],
                    'product_id' => (int) $product->id,
                    'cantidad' => $quantity,
                    'precio_momento' => $price,
                    'retorna' => false,
                    'observaciones_item' => null,
                ];
            }

            self::assertNoDuplicateSkuLines($resolvedRows);

            $finalByProduct = self::sumRowsByProduct($resolvedRows);
            $originalValueByProduct = self::sumValueByProduct($originalItems->all());
            $finalValueByProduct = self::sumRowsValueByProduct($resolvedRows);
            $allProductIds = array_values(array_unique(array_merge(array_keys($originalByProduct), array_keys($finalByProduct))));
            $productsForStock = Product::query()
                ->whereIn('id', $allProductIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($allProductIds as $productId) {
                $product = $productsForStock->get($productId);

                if (! $product) {
                    continue;
                }

                $originalQty = $originalByProduct[$productId] ?? 0;
                $finalQty = $finalByProduct[$productId] ?? 0;
                $newStock = (int) $product->stock_actual + ($finalQty - $originalQty);

                if ($newStock < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'El ajuste deja stock negativo para el SKU ' . $product->sku . '.',
                    ]);
                }

                if ($newStock < (int) $product->stock_minimo) {
                    $criticalSkus[] = (string) $product->sku;
                }

                $product->stock_actual = $newStock;
                $product->fecha_ultima_entrada = now()->toDateString();

                $currentStock = (int) ($product->stock_actual - ($finalQty - $originalQty));
                $currentValue = ((float) ($product->precio_unitario ?? 0)) * $currentStock;
                $originalValue = (float) ($originalValueByProduct[$productId] ?? 0);
                $finalValue = (float) ($finalValueByProduct[$productId] ?? 0);

                $baseValue = $currentValue - $originalValue;
                $newValue = $baseValue + $finalValue;

                $product->precio_unitario = $newStock > 0
                    ? round(max(0, $newValue / $newStock), 2)
                    : 0;

                $product->save();
            }

            $movement->update(array_merge($movementData, [
                'total_items' => count($resolvedRows),
            ]));

            foreach ($removedByItemId as $itemId => $reason) {
                $original = $originalItems->get($itemId);
                DB::table('movement_item_removal_logs')->insert([
                    'movement_id' => $movement->id,
                    'movement_item_id' => $original?->id,
                    'product_id' => $original?->product_id,
                    'sku_snapshot' => (string) ($original?->product?->sku ?? ''),
                    'cantidad' => (int) ($original?->cantidad ?? 0),
                    'motivo' => (string) $reason,
                    'removed_by_user_id' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            MovementItem::query()->where('movement_id', $movement->id)->delete();

            foreach ($resolvedRows as $row) {
                MovementItem::create([
                    'movement_id' => $movement->id,
                    'product_id' => $row['product_id'],
                    'cantidad' => $row['cantidad'],
                    'precio_momento' => $row['precio_momento'],
                    'retorna' => false,
                    'observaciones_item' => null,
                ]);
            }

            if ((bool) config('app.auto_archive_on_ingreso_line_removal', true) && $removedByItemId !== []) {
                $removedProductIds = collect(array_keys($removedByItemId))
                    ->map(fn (int $itemId): ?int => $originalItems->get($itemId)?->product_id)
                    ->filter(fn (?int $productId): bool => $productId !== null && $productId > 0)
                    ->unique()
                    ->values()
                    ->all();

                if ($removedProductIds !== []) {
                    $productsToEvaluate = Product::query()
                        ->whereIn('id', $removedProductIds)
                        ->lockForUpdate()
                        ->get();

                    foreach ($productsToEvaluate as $product) {
                        $hasAnyMovement = MovementItem::query()
                            ->where('product_id', $product->id)
                            ->exists();

                        if ((int) $product->stock_actual === 0 && ! $hasAnyMovement && ! $product->is_archived) {
                            $product->is_archived = true;
                            $product->save();
                        }
                    }
                }
            }
        });

        self::notifyCriticalProducts($criticalSkus, 'ajuste de ingreso');
    }

    private static function notifyCriticalProducts(array $criticalSkus, string $context): void
    {
        $criticalSkus = array_values(array_unique(array_filter(array_map('strval', $criticalSkus))));

        if ($criticalSkus === []) {
            return;
        }

        $preview = array_slice($criticalSkus, 0, 5);
        $body = 'SKUs en critico: ' . implode(', ', $preview);

        if (count($criticalSkus) > 5) {
            $body .= ' y ' . (count($criticalSkus) - 5) . ' mas.';
        }

        Notification::make()
            ->title('Productos en estado critico tras ' . $context)
            ->body($body)
            ->warning()
            ->send();
    }

    private static function normalizeRows(array $itemsData, int $movementSign): array
    {
        return array_values(array_map(function (array $row) use ($movementSign): array {
            $quantity = max(0, (int) ($row['cantidad'] ?? 0));
            $productId = (int) ($row['product_id'] ?? 0);
            $price = isset($row['precio_momento']) ? (float) $row['precio_momento'] : null;

            return [
                'movement_item_id' => isset($row['movement_item_id']) ? (int) $row['movement_item_id'] : null,
                'product_id' => $productId,
                'cantidad' => $quantity,
                'precio_momento' => $price,
                'retorna' => (bool) ((int) ($row['retorna'] ?? 0)),
                'observaciones_item' => trim((string) ($row['observaciones_item'] ?? '')),
                'eliminar_linea' => (bool) ($row['eliminar_linea'] ?? false),
                'motivo_eliminacion' => Arr::get($row, 'motivo_eliminacion'),
            ];
        }, $itemsData));
    }

    private static function normalizeIngresoRows(array $itemsData): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'movement_item_id' => isset($row['movement_item_id']) ? (int) $row['movement_item_id'] : null,
                'product_id' => (int) ($row['product_id'] ?? 0),
                'sku' => trim((string) ($row['sku'] ?? '')),
                'subcategory_id' => (int) ($row['subcategory_id'] ?? 0),
                'descripcion' => trim((string) ($row['descripcion'] ?? '')),
                'marca' => trim((string) ($row['marca'] ?? '')),
                'serial' => trim((string) ($row['serial'] ?? '')),
                'estado' => trim((string) ($row['estado'] ?? '')),
                'medida' => trim((string) ($row['medida'] ?? '')),
                'ubicacion' => trim((string) ($row['ubicacion'] ?? '')),
                'dpto_responsable' => trim((string) ($row['dpto_responsable'] ?? '')),
                'stock_minimo' => max(0, (int) ($row['stock_minimo'] ?? 0)),
                'cantidad' => max(0, (int) ($row['cantidad'] ?? 0)),
                'precio_momento' => (float) ($row['precio_momento'] ?? 0),
                'retorna' => false,
                'observaciones_item' => null,
                'eliminar_linea' => (bool) ($row['eliminar_linea'] ?? false),
                'motivo_eliminacion' => Arr::get($row, 'motivo_eliminacion'),
            ];
        }, $itemsData));
    }

    private static function extractRemovedItems(array $rows): array
    {
        $removed = [];

        foreach ($rows as $row) {
            if (! $row['eliminar_linea'] || ! $row['movement_item_id']) {
                continue;
            }

            $removed[$row['movement_item_id']] = $row['motivo_eliminacion'];
        }

        return $removed;
    }

    private static function sumByProduct(array $items): array
    {
        $sum = [];

        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            if ($productId <= 0) {
                continue;
            }

            $sum[$productId] = ($sum[$productId] ?? 0) + (int) $item->cantidad;
        }

        return $sum;
    }

    private static function sumRowsByProduct(array $rows): array
    {
        $sum = [];

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['cantidad'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $sum[$productId] = ($sum[$productId] ?? 0) + $qty;
        }

        return $sum;
    }

    private static function sumValueByProduct(array $items): array
    {
        $sum = [];

        foreach ($items as $item) {
            $productId = (int) ($item->product_id ?? 0);
            $qty = (int) ($item->cantidad ?? 0);
            $price = (float) ($item->precio_momento ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $sum[$productId] = ($sum[$productId] ?? 0) + ($qty * $price);
        }

        return $sum;
    }

    private static function sumRowsValueByProduct(array $rows): array
    {
        $sum = [];

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['cantidad'] ?? 0);
            $price = (float) ($row['precio_momento'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $sum[$productId] = ($sum[$productId] ?? 0) + ($qty * $price);
        }

        return $sum;
    }

    private static function assertNoDuplicateSkuLines(array $rows): void
    {
        $seen = [];

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);

            if ($productId <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Cada linea activa debe tener un SKU valido.',
                ]);
            }

            if (isset($seen[$productId])) {
                throw ValidationException::withMessages([
                    'items' => 'No se permite repetir el mismo SKU en una misma entrada/salida. Usa una sola linea por SKU.',
                ]);
            }

            $seen[$productId] = true;
        }
    }
}
