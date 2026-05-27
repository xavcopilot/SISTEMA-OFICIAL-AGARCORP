<?php

namespace App\Support;

use App\Models\SolicitudCompraItem;
use Illuminate\Support\Facades\DB;

class SolicitudItemTrackingService
{
    /**
     * @param  array<int>  $solicitudCompraItemIds
     */
    public static function syncByItemIds(array $solicitudCompraItemIds): void
    {
        $itemIds = collect($solicitudCompraItemIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return;
        }

        $items = SolicitudCompraItem::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'cantidad_solicitada', 'cantidad_a_comprar']);

        if ($items->isEmpty()) {
            return;
        }

        $sumarioByItem = DB::table('sumario_items')
            ->selectRaw('solicitud_compra_item_id, COALESCE(SUM(cantidad), 0) AS total')
            ->join('sumarios', 'sumarios.id', '=', 'sumario_items.sumario_id')
            ->whereIn('solicitud_compra_item_id', $itemIds)
            ->where(function ($query): void {
                $query
                    ->whereNull('sumarios.workflow_estado')
                    ->orWhere('sumarios.workflow_estado', '!=', 'RECHAZADO');
            })
            ->groupBy('solicitud_compra_item_id')
            ->pluck('total', 'solicitud_compra_item_id');

        $ocByItem = DB::table('orden_compra_items')
            ->selectRaw('solicitud_compra_item_id, COALESCE(SUM(cantidad), 0) AS total')
            ->whereIn('solicitud_compra_item_id', $itemIds)
            ->groupBy('solicitud_compra_item_id')
            ->pluck('total', 'solicitud_compra_item_id');

        foreach ($items as $item) {
            $cantidadPedida = round((float) ($item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);
            $cantidadEnSumario = round((float) ($sumarioByItem[$item->id] ?? 0), 2);
            $cantidadComprada = round((float) ($ocByItem[$item->id] ?? 0), 2);

            $estadoItem = 'SIN_PROCESAR';
            if ($cantidadComprada > 0) {
                $estadoItem = 'EN_OC';
            } elseif ($cantidadEnSumario > 0) {
                $estadoItem = 'EN_SUMARIO';
            }

            SolicitudCompraItem::query()
                ->whereKey($item->id)
                ->update([
                    'cantidad_pedida' => $cantidadPedida,
                    'cantidad_en_sumario' => $cantidadEnSumario,
                    'cantidad_comprada' => $cantidadComprada,
                    'estado_item' => $estadoItem,
                ]);
        }
    }
}
