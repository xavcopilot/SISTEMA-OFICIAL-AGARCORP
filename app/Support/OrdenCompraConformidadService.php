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

                OrdenCompraItem::query()->whereKey($row['orden_compra_item_id'])->update([
                    'decision_solicitante' => $row['decision'],
                    'motivo_rechazo_solicitante' => $row['decision'] === 'RECHAZADO' ? $row['motivo'] : null,
                    'conformidad_solicitante_at' => $now,
                    'estado_recepcion' => $row['decision'] === 'ACEPTADO' ? 'ENTREGADO_SOLICITANTE' : 'ZONA_TRANSICION',
                    'entregado_at' => $row['decision'] === 'ACEPTADO' ? $now : null,
                ]);
            }

            $ordenCompra->refresh()->load('items');

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
