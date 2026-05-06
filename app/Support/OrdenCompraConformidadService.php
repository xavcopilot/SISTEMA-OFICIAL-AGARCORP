<?php

namespace App\Support;

use App\Models\InventoryMovement;
use App\Models\MovementItem;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Product;
use App\Models\SolicitudCompraItem;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdenCompraConformidadService
{
    public function registrarConformidadPorItems(OrdenCompra $ordenCompra, User $user, array $rows): OrdenCompra
    {
        return DB::transaction(function () use ($ordenCompra, $user, $rows): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            if (! $ordenCompra->recepcion_procesada_at) {
                throw new \RuntimeException('La ODC aun no esta en zona de transicion.');
            }

            $payload = collect($rows)
                ->map(fn (array $row): array => [
                    'orden_compra_item_id' => (int) ($row['orden_compra_item_id'] ?? 0),
                    'decision' => strtoupper(trim((string) ($row['decision'] ?? ''))),
                    'cantidad_llegada' => round((float) ($row['cantidad_llegada_raw'] ?? $row['cantidad_llegada'] ?? 0), 2),
                    'cantidad_rechazada' => filled($row['cantidad_rechazada'] ?? null)
                        ? round((float) $row['cantidad_rechazada'], 2)
                        : null,
                    'motivo' => trim((string) ($row['motivo'] ?? '')),
                ])
                ->filter(fn (array $row): bool => $row['orden_compra_item_id'] > 0)
                ->values();

            if ($payload->isEmpty()) {
                throw new \RuntimeException('No hay decisiones de conformidad para procesar.');
            }

            $allowedIds = $ordenCompra->items->pluck('id')->all();
            $now = now();

            foreach ($payload as $row) {
                if (! in_array($row['orden_compra_item_id'], $allowedIds, true)) {
                    continue;
                }

                if (! in_array($row['decision'], ['ACEPTADO', 'RECHAZADO'], true)) {
                    throw new \RuntimeException('Cada item debe marcarse como ACEPTADO o RECHAZADO.');
                }

                if ($row['decision'] === 'RECHAZADO' && $row['motivo'] === '') {
                    throw new \RuntimeException('Debes indicar motivo para cada item rechazado.');
                }

                $item = $ordenCompra->items->firstWhere('id', $row['orden_compra_item_id']);

                if (! $item) {
                    continue;
                }

                $cantidadOriginal = round((float) ($item->cantidad ?? $row['cantidad_llegada']), 2);

                if ($cantidadOriginal <= 0) {
                    throw new \RuntimeException('La cantidad llegada del item no es valida.');
                }

                if ($row['decision'] === 'ACEPTADO') {
                    $item->forceFill([
                        'decision_solicitante' => 'ACEPTADO',
                        'motivo_rechazo_solicitante' => null,
                        'conformidad_solicitante_at' => $now,
                        'estado_recepcion' => 'ENTREGADO_SOLICITANTE',
                        'entregado_at' => $now,
                    ])->save();

                    continue;
                }

                $cantidadRechazada = $row['cantidad_rechazada'] ?? $cantidadOriginal;

                if ($cantidadRechazada <= 0) {
                    throw new \RuntimeException('Debes indicar una cantidad rechazada mayor a cero.');
                }

                if ($cantidadRechazada > $cantidadOriginal) {
                    throw new \RuntimeException('La cantidad rechazada no puede ser mayor que la cantidad llegada.');
                }

                $cantidadAceptada = round($cantidadOriginal - $cantidadRechazada, 2);

                if ($cantidadAceptada > 0) {
                    $item->forceFill([
                        'cantidad' => $cantidadAceptada,
                        'precio_total' => round($cantidadAceptada * (float) $item->precio_unitario, 2),
                        'decision_solicitante' => 'ACEPTADO',
                        'motivo_rechazo_solicitante' => null,
                        'conformidad_solicitante_at' => $now,
                        'estado_recepcion' => 'ENTREGADO_SOLICITANTE',
                        'entregado_at' => $now,
                    ])->save();

                    OrdenCompraItem::query()->create([
                        'orden_compra_id' => (int) $item->orden_compra_id,
                        'sumario_item_id' => $item->sumario_item_id,
                        'solicitud_compra_item_id' => $item->solicitud_compra_item_id,
                        'item' => $item->item,
                        'descripcion' => $item->descripcion,
                        'unidad_medida' => $item->unidad_medida,
                        'cantidad' => $cantidadRechazada,
                        'precio_unitario' => $item->precio_unitario,
                        'precio_total' => round($cantidadRechazada * (float) $item->precio_unitario, 2),
                        'estado_recepcion' => 'ZONA_TRANSICION',
                        'en_transicion_at' => $item->en_transicion_at,
                        'entregado_at' => null,
                        'decision_solicitante' => 'RECHAZADO',
                        'motivo_rechazo_solicitante' => $row['motivo'],
                        'conformidad_solicitante_at' => $now,
                        'procesado_almacen_at' => null,
                        'modo_ingreso_almacen' => null,
                        'product_id' => null,
                    ]);

                    continue;
                }

                $item->forceFill([
                    'decision_solicitante' => 'RECHAZADO',
                    'motivo_rechazo_solicitante' => $row['motivo'],
                    'conformidad_solicitante_at' => $now,
                    'estado_recepcion' => 'ZONA_TRANSICION',
                    'entregado_at' => null,
                ])->save();
            }

            $ordenCompra->refresh()->load('items');

            app(SolicitudCompraCompletionService::class)->syncFromOrdenCompra($ordenCompra);

            $hasRejected = $ordenCompra->items->contains(fn (OrdenCompraItem $item): bool => (string) $item->decision_solicitante === 'RECHAZADO');
            $allDecided = ! $ordenCompra->items->contains(fn (OrdenCompraItem $item): bool => blank($item->decision_solicitante));

            if (! $allDecided) {
                return $ordenCompra;
            }

            $ordenCompra->forceFill([
                'conformidad_solicitante_at' => $now,
                'conformidad_por_user_id' => $user->id,
                'devolucion_solicitada_at' => $hasRejected ? $now : null,
                'devolucion_solicitada_por_user_id' => $hasRejected ? $user->id : null,
                'workflow_post_compra' => $hasRejected ? 'RECHAZADA_SOLICITANTE' : 'CONFORMIDAD_POR_ITEMS_COMPLETA',
            ])->save();

            return $ordenCompra->fresh(['items', 'sumario.solicitudCompra']);
        });
    }

    public function procesarEntradaPorItems(OrdenCompra $ordenCompra, User $user, array $rows): OrdenCompra
    {
        return DB::transaction(function () use ($ordenCompra, $user, $rows): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra', 'proveedor'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            $payload = collect($rows)
                ->map(fn (array $row): array => [
                    'orden_compra_item_id' => (int) ($row['orden_compra_item_id'] ?? 0),
                    'modo' => strtoupper(trim((string) ($row['modo'] ?? ''))),
                    'product_id' => filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null,
                ])
                ->filter(fn (array $row): bool => $row['orden_compra_item_id'] > 0)
                ->values();

            if ($payload->isEmpty()) {
                throw new \RuntimeException('No hay items para procesar en almacen.');
            }

            $acceptedItems = $ordenCompra->items
                ->where('decision_solicitante', 'ACEPTADO')
                ->whereNull('procesado_almacen_at')
                ->keyBy('id');

            if ($acceptedItems->isEmpty()) {
                throw new \RuntimeException('No hay items aceptados pendientes de entrada final.');
            }

            $movement = $this->crearMovimientoEntrada($ordenCompra, $user);
            $processedCount = 0;
            $line = 1;

            foreach ($payload as $row) {
                $item = $acceptedItems->get($row['orden_compra_item_id']);

                if (! $item) {
                    continue;
                }

                if (! in_array($row['modo'], ['ENTRADA', 'REGISTRO_NUEVO'], true)) {
                    throw new \RuntimeException('Modo de ingreso invalido para item #' . $item->id . '.');
                }

                $cantidad = max(1, (int) round((float) $item->cantidad));
                $precioUnitario = round((float) $item->precio_unitario, 2);

                if ($row['modo'] === 'ENTRADA') {
                    if (! $row['product_id']) {
                        throw new \RuntimeException('Debes seleccionar un producto existente para ENTRADA.');
                    }

                    $product = Product::query()->find($row['product_id']);

                    if (! $product) {
                        throw new \RuntimeException('El producto seleccionado para ENTRADA no existe.');
                    }

                    $product->forceFill([
                        'stock_actual' => (int) $product->stock_actual + $cantidad,
                        'precio_unitario' => $precioUnitario,
                        'fecha_ultima_entrada' => now()->toDateString(),
                    ])->save();
                } else {
                    $product = $this->crearProductoNuevo($ordenCompra, $item, $movement, $line);
                }

                MovementItem::query()->create([
                    'movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => $precioUnitario,
                    'retorna' => false,
                ]);

                $item->forceFill([
                    'product_id' => $product->id,
                    'modo_ingreso_almacen' => $row['modo'],
                    'procesado_almacen_at' => now(),
                ])->save();

                $this->cerrarItemSolicitudOriginal($item);

                $processedCount++;
                $line++;
            }

            if ($processedCount <= 0) {
                throw new \RuntimeException('No se proceso ningun item aceptado.');
            }

            $remaining = $ordenCompra->items()
                ->where('decision_solicitante', 'ACEPTADO')
                ->whereNull('procesado_almacen_at')
                ->exists();

            $movement->forceFill([
                'total_items' => (int) $movement->items()->count(),
            ])->save();

            $ordenCompra->forceFill([
                'inventario_movimiento_id' => $movement->id,
                'factura_pendiente' => false,
                'workflow_post_compra' => $remaining ? 'CONFORMIDAD_POR_ITEMS_COMPLETA' : 'CERRADA_CONFORME',
            ])->save();

            app(SolicitudCompraCompletionService::class)->syncFromOrdenCompra($ordenCompra);

            return $ordenCompra->fresh(['items', 'sumario.solicitudCompra', 'inventarioMovimiento']);
        });
    }

    public function procesarEntradaDetallada(OrdenCompra $ordenCompra, User $user, array $movementData, array $rows): OrdenCompra
    {
        return DB::transaction(function () use ($ordenCompra, $user, $movementData, $rows): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra', 'proveedor'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            $payload = collect($rows)
                ->map(fn (array $row): array => [
                    'orden_compra_item_id' => (int) ($row['orden_compra_item_id'] ?? 0),
                    'product_id' => filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null,
                    'cantidad' => max(1, (int) round((float) ($row['cantidad'] ?? 0))),
                    'precio' => round((float) ($row['precio'] ?? 0), 2),
                ])
                ->filter(fn (array $row): bool => $row['orden_compra_item_id'] > 0)
                ->values()
                ->all();

            if ($payload === []) {
                throw new \RuntimeException('No hay items para procesar en almacen.');
            }

            $this->assertNoDuplicateProductsInItems($payload, 'entrada');

            $acceptedItems = $ordenCompra->items
                ->where('decision_solicitante', 'ACEPTADO')
                ->whereNull('procesado_almacen_at')
                ->keyBy('id');

            if ($acceptedItems->isEmpty()) {
                throw new \RuntimeException('No hay items aceptados pendientes de entrada final.');
            }

            $movement = $this->crearMovimientoDesdeDatos('entrada', $ordenCompra, $user, $movementData);
            $processedCount = 0;

            foreach ($payload as $row) {
                $item = $acceptedItems->get($row['orden_compra_item_id']);

                if (! $item) {
                    continue;
                }

                if (! $row['product_id']) {
                    throw new \RuntimeException('Debes seleccionar un producto existente para ENTRADA.');
                }

                $product = Product::query()->find($row['product_id']);

                if (! $product) {
                    throw new \RuntimeException('El producto seleccionado para ENTRADA no existe.');
                }

                $cantidad = $row['cantidad'] > 0 ? $row['cantidad'] : max(1, (int) round((float) $item->cantidad));
                $precio = $row['precio'] > 0 ? $row['precio'] : round((float) $item->precio_unitario, 2);

                $stockAnterior = (int) ($product->stock_actual ?? 0);
                $precioAnterior = (float) ($product->precio_unitario ?? 0);

                $product->forceFill([
                    'stock_actual' => $stockAnterior + $cantidad,
                    'precio_unitario' => $this->calculateWeightedAverageUnitPrice($stockAnterior, $precioAnterior, $cantidad, $precio),
                    'fecha_ultima_entrada' => now()->toDateString(),
                ])->save();

                MovementItem::query()->create([
                    'movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => $precio,
                    'retorna' => false,
                ]);

                $item->forceFill([
                    'product_id' => $product->id,
                    'modo_ingreso_almacen' => 'ENTRADA',
                    'procesado_almacen_at' => now(),
                ])->save();

                $this->cerrarItemSolicitudOriginal($item);
                $processedCount++;
            }

            return $this->finalizarProcesamientoAlmacen($ordenCompra, $movement, $processedCount);
        });
    }

    public function procesarRegistroNuevoDetallado(OrdenCompra $ordenCompra, User $user, array $movementData, array $rows): OrdenCompra
    {
        return DB::transaction(function () use ($ordenCompra, $user, $movementData, $rows): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra', 'proveedor'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            $payload = collect($rows)
                ->map(fn (array $row): array => [
                    'orden_compra_item_id' => (int) ($row['orden_compra_item_id'] ?? 0),
                    'subcategory_id' => (int) ($row['subcategory_id'] ?? 0),
                    'descripcion' => trim((string) ($row['descripcion'] ?? '')),
                    'marca' => trim((string) ($row['marca'] ?? '')),
                    'serial' => trim((string) ($row['serial'] ?? '')),
                    'estado' => trim((string) ($row['estado'] ?? 'NUEVO')),
                    'medida' => trim((string) ($row['medida'] ?? 'UND')),
                    'cantidad' => max(1, (int) round((float) ($row['cantidad'] ?? 0))),
                    'ubicacion' => trim((string) ($row['ubicacion'] ?? 'ALMACEN')),
                    'dpto_responsable' => trim((string) ($row['dpto_responsable'] ?? 'GENERAL')),
                    'rango_min' => max(0, (int) ($row['rango_min'] ?? 0)),
                    'precio' => round((float) ($row['precio'] ?? 0), 2),
                ])
                ->filter(fn (array $row): bool => $row['orden_compra_item_id'] > 0)
                ->values()
                ->all();

            if ($payload === []) {
                throw new \RuntimeException('No hay items para procesar en almacen.');
            }

            $acceptedItems = $ordenCompra->items
                ->where('decision_solicitante', 'ACEPTADO')
                ->whereNull('procesado_almacen_at')
                ->keyBy('id');

            if ($acceptedItems->isEmpty()) {
                throw new \RuntimeException('No hay items aceptados pendientes de registro nuevo.');
            }

            $movement = $this->crearMovimientoDesdeDatos('ingreso', $ordenCompra, $user, $movementData);
            $processedCount = 0;
            $line = 1;

            foreach ($payload as $row) {
                $item = $acceptedItems->get($row['orden_compra_item_id']);

                if (! $item) {
                    continue;
                }

                if ($row['subcategory_id'] <= 0) {
                    throw new \RuntimeException('Debes seleccionar una subcategoría para REGISTRO NUEVO.');
                }

                if ($row['descripcion'] === '') {
                    throw new \RuntimeException('La descripción es obligatoria para REGISTRO NUEVO.');
                }

                $cantidad = $row['cantidad'] > 0 ? $row['cantidad'] : max(1, (int) round((float) $item->cantidad));
                $precio = $row['precio'] > 0 ? $row['precio'] : round((float) $item->precio_unitario, 2);

                $product = Product::query()->create([
                    'cod_ingreso' => $movement->nro_control . '-' . str_pad((string) $line, 3, '0', STR_PAD_LEFT),
                    'descripcion' => $row['descripcion'],
                    'marca' => $row['marca'] !== '' ? $row['marca'] : (string) ($ordenCompra->proveedor?->nombre ?? ''),
                    'subcategory_id' => $row['subcategory_id'],
                    'serial' => $row['serial'] !== '' ? $row['serial'] : 'ODC-' . (string) $ordenCompra->id . '-' . (string) $item->id . '-' . (string) now()->timestamp,
                    'estado' => $row['estado'] !== '' ? $row['estado'] : 'NUEVO',
                    'medida' => $row['medida'] !== '' ? $row['medida'] : (string) ($item->unidad_medida ?? 'UND'),
                    'ubicacion' => $row['ubicacion'] !== '' ? $row['ubicacion'] : 'ALMACEN',
                    'dpto_responsable' => $row['dpto_responsable'] !== '' ? $row['dpto_responsable'] : (string) ($ordenCompra->sumario?->solicitudCompra?->departamento_solicitante ?? 'GENERAL'),
                    'stock_minimo' => $row['rango_min'],
                    'stock_actual' => $cantidad,
                    'precio_unitario' => $precio,
                    'fecha_adquisicion' => now()->toDateString(),
                    'fecha_ultima_entrada' => now()->toDateString(),
                ]);

                MovementItem::query()->create([
                    'movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => $precio,
                    'retorna' => false,
                ]);

                $item->forceFill([
                    'product_id' => $product->id,
                    'modo_ingreso_almacen' => 'REGISTRO_NUEVO',
                    'procesado_almacen_at' => now(),
                ])->save();

                $this->cerrarItemSolicitudOriginal($item);
                $processedCount++;
                $line++;
            }

            return $this->finalizarProcesamientoAlmacen($ordenCompra, $movement, $processedCount);
        });
    }

    public function aceptar(OrdenCompra $ordenCompra, User $user): OrdenCompra
    {
        $rows = $ordenCompra->items()
            ->get(['id'])
            ->map(fn (OrdenCompraItem $item): array => [
                'orden_compra_item_id' => $item->id,
                'decision' => 'ACEPTADO',
            ])
            ->all();

        $ordenCompra = $this->registrarConformidadPorItems($ordenCompra, $user, $rows);

        $entryRows = $ordenCompra->items()
            ->where('decision_solicitante', 'ACEPTADO')
            ->map(fn (OrdenCompraItem $item): array => [
                'orden_compra_item_id' => $item->id,
                'modo' => 'REGISTRO_NUEVO',
            ])
            ->all();

        return $this->procesarEntradaPorItems($ordenCompra, $user, $entryRows);
    }

    private function crearMovimientoEntrada(OrdenCompra $ordenCompra, User $user): InventoryMovement
    {
        $solicitud = $ordenCompra->sumario?->solicitudCompra;

        return InventoryMovement::query()->create([
            'tipo' => 'ingreso',
            'orden_compra' => (string) $ordenCompra->correlativo_odc,
            'nro_solicitud' => (string) ($solicitud?->codigo_control ?? ''),
            'factura_nota' => strtolower((string) ($ordenCompra->tipo_documento_recepcion ?? '')), 
            'nro_doc_legal' => (string) $ordenCompra->correlativo_odc,
            'proveedor' => (string) ($ordenCompra->proveedor?->nombre ?? ''),
            'almacenista' => (string) ($user->name ?? 'Almacen'),
            'comentarios' => 'Entrada oficial por items aceptados para ODC ' . (string) $ordenCompra->correlativo_odc,
        ]);
    }

    private function crearMovimientoDesdeDatos(string $tipo, OrdenCompra $ordenCompra, User $user, array $data): InventoryMovement
    {
        $solicitud = $ordenCompra->sumario?->solicitudCompra;

        return InventoryMovement::query()->create([
            'tipo' => $tipo,
            'nro_control' => $data['nro_control'] ?? InventoryMovement::generateControlNumber($tipo),
            'orden_compra' => $data['orden_compra'] ?? (string) $ordenCompra->correlativo_odc,
            'nro_solicitud' => $data['nro_solicitud'] ?? (string) ($solicitud?->codigo_control ?? ''),
            'factura_nota' => $data['factura_nota'] ?? strtolower((string) ($ordenCompra->tipo_documento_recepcion ?? '')),
            'nro_doc_legal' => $data['nro_doc_legal'] ?? (string) $ordenCompra->correlativo_odc,
            'proveedor' => $data['proveedor'] ?? (string) ($ordenCompra->proveedor?->nombre ?? ''),
            'almacenista' => $data['almacenista_visual'] ?? (string) ($user->name ?? 'Almacen'),
            'comentarios' => $data['comentarios'] ?? ('Entrada oficial por items aceptados para ODC ' . (string) $ordenCompra->correlativo_odc),
        ]);
    }

    private function finalizarProcesamientoAlmacen(OrdenCompra $ordenCompra, InventoryMovement $movement, int $processedCount): OrdenCompra
    {
        if ($processedCount <= 0) {
            throw new \RuntimeException('No se proceso ningun item aceptado.');
        }

        $remaining = $ordenCompra->items()
            ->where('decision_solicitante', 'ACEPTADO')
            ->whereNull('procesado_almacen_at')
            ->exists();

        $movement->forceFill([
            'total_items' => (int) $movement->items()->count(),
        ])->save();

        $ordenCompra->forceFill([
            'inventario_movimiento_id' => $movement->id,
            'factura_pendiente' => false,
            'workflow_post_compra' => $remaining ? 'CONFORMIDAD_POR_ITEMS_COMPLETA' : 'CERRADA_CONFORME',
        ])->save();

        app(SolicitudCompraCompletionService::class)->syncFromOrdenCompra($ordenCompra);

        return $ordenCompra->fresh(['items', 'sumario.solicitudCompra', 'inventarioMovimiento']);
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

    private function assertNoDuplicateProductsInItems(array $items, string $tipo): void
    {
        $seen = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            if (isset($seen[$productId])) {
                throw ValidationException::withMessages([
                    'items' => 'No se permite repetir el mismo SKU en una misma ' . $tipo . '. Usa una sola linea por SKU.',
                ]);
            }

            $seen[$productId] = true;
        }
    }

    private function crearProductoNuevo(OrdenCompra $ordenCompra, OrdenCompraItem $item, InventoryMovement $movement, int $line): Product
    {
        $solicitud = $ordenCompra->sumario?->solicitudCompra;
        $subcategoryId = (int) (Subcategory::query()->orderBy('id')->value('id') ?? 0);

        if ($subcategoryId <= 0) {
            throw new \RuntimeException('No hay subcategorias registradas para crear productos en inventario.');
        }

        $cantidad = max(1, (int) round((float) $item->cantidad));
        $precioUnitario = round((float) $item->precio_unitario, 2);

        return Product::query()->create([
            'cod_ingreso' => $movement->nro_control . '-' . str_pad((string) $line, 3, '0', STR_PAD_LEFT),
            'descripcion' => (string) ($item->descripcion ?? 'Articulo comprado'),
            'marca' => (string) ($ordenCompra->proveedor?->nombre ?? 'SIN MARCA'),
            'subcategory_id' => $subcategoryId,
            'serial' => 'ODC-' . (string) $ordenCompra->id . '-' . (string) $item->id . '-' . (string) now()->timestamp,
            'estado' => 'NUEVO',
            'medida' => (string) ($item->unidad_medida ?? 'UND'),
            'ubicacion' => 'ALMACEN',
            'dpto_responsable' => (string) ($solicitud?->departamento_solicitante ?? 'GENERAL'),
            'stock_minimo' => 0,
            'stock_actual' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'fecha_adquisicion' => now()->toDateString(),
            'fecha_ultima_entrada' => now()->toDateString(),
        ]);
    }

    private function cerrarItemSolicitudOriginal(OrdenCompraItem $item): void
    {
        // El cierre final del item se recalcula por cantidad procesada acumulada.
    }
}
