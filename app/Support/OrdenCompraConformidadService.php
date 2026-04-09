<?php

namespace App\Support;

use App\Models\InventoryMovement;
use App\Models\MovementItem;
use App\Models\OrdenCompra;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrdenCompraConformidadService
{
    public function aceptar(OrdenCompra $ordenCompra, User $user): OrdenCompra
    {
        return DB::transaction(function () use ($ordenCompra, $user): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            if (! $ordenCompra->recepcion_procesada_at) {
                throw new \RuntimeException('La recepcion aun no ha sido procesada.');
            }

            if ($ordenCompra->conformidad_solicitante_at) {
                return $ordenCompra;
            }

            $movement = $this->registrarIngresoOficial($ordenCompra);
            $now = now();

            $ordenCompra->items()->update([
                'estado_recepcion' => 'ENTREGADO_SOLICITANTE',
                'entregado_at' => $now,
            ]);

            $ordenCompra->forceFill([
                'conformidad_solicitante_at' => $now,
                'conformidad_por_user_id' => $user->id,
                'inventario_movimiento_id' => $movement->id,
                'factura_pendiente' => false,
            ])->save();

            return $ordenCompra->fresh(['inventarioMovimiento', 'sumario.solicitudCompra']);
        });
    }

    private function registrarIngresoOficial(OrdenCompra $ordenCompra): InventoryMovement
    {
        $solicitud = $ordenCompra->sumario?->solicitudCompra;
        $subcategoryId = (int) (Subcategory::query()->orderBy('id')->value('id') ?? 0);

        if ($subcategoryId <= 0) {
            throw new \RuntimeException('No hay subcategorias registradas para crear productos en inventario.');
        }

        $movement = InventoryMovement::query()->create([
            'tipo' => 'ingreso',
            'orden_compra' => (string) $ordenCompra->correlativo_odc,
            'nro_solicitud' => (string) ($solicitud?->codigo_control ?? ''),
            'factura_nota' => strtolower((string) ($ordenCompra->tipo_documento_recepcion ?? '')), 
            'nro_doc_legal' => (string) $ordenCompra->correlativo_odc,
            'proveedor' => (string) ($ordenCompra->proveedor?->nombre ?? ''),
            'almacenista' => auth()->user()?->name,
            'comentarios' => 'Entrada oficial generada por conformidad del solicitante para ODC ' . (string) $ordenCompra->correlativo_odc,
        ]);

        $line = 1;

        foreach ($ordenCompra->items as $item) {
            $cantidad = max(1, (int) round((float) $item->cantidad));
            $precioUnitario = round((float) $item->precio_unitario, 2);

            $product = Product::query()->create([
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

            MovementItem::query()->create([
                'movement_id' => $movement->id,
                'product_id' => $product->id,
                'cantidad' => $cantidad,
                'precio_momento' => $precioUnitario,
                'retorna' => false,
            ]);

            $line++;
        }

        $movement->forceFill([
            'total_items' => $ordenCompra->items->count(),
        ])->save();

        return $movement;
    }
}
