<?php

namespace App\Support;

use App\Models\OrdenCompra;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;

class SolicitudCompraCompletionService
{
    public function syncFromOrdenCompra(OrdenCompra $ordenCompra): void
    {
        $solicitud = $ordenCompra->sumario?->solicitudCompra;

        if (! $solicitud) {
            return;
        }

        $this->syncSolicitud($solicitud->id);
    }

    public function syncSolicitud(int $solicitudId): void
    {
        $solicitud = SolicitudCompra::query()
            ->with('items')
            ->find($solicitudId);

        if (! $solicitud || $solicitud->items->isEmpty()) {
            return;
        }

        foreach ($solicitud->items as $item) {
            $cantidadObjetivo = round((float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);
            $cantidadProcesada = round((float) SolicitudCompraItem::query()
                ->whereKey($item->id)
                ->withSum([
                    'ordenCompraItems as cantidad_procesada_almacen' => fn ($query) => $query
                        ->whereNotNull('procesado_almacen_at')
                        ->where('decision_solicitante', 'ACEPTADO'),
                ], 'cantidad')
                ->value('cantidad_procesada_almacen'), 2);

            $estado = $cantidadProcesada >= $cantidadObjetivo && $cantidadObjetivo > 0
                ? 'CERRADO'
                : ((float) $cantidadProcesada > 0 ? 'EN_OC' : (string) ($item->estado_item ?: 'SIN_PROCESAR'));

            $item->forceFill([
                'estado_item' => $estado,
            ])->save();
        }

        $solicitud->refresh()->load('items');

        $allCompleted = $solicitud->items->every(function (SolicitudCompraItem $item): bool {
            $cantidadObjetivo = round((float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);
            $cantidadProcesada = round((float) $item->ordenCompraItems()
                ->whereNotNull('procesado_almacen_at')
                ->where('decision_solicitante', 'ACEPTADO')
                ->sum('cantidad'), 2);

            return $cantidadObjetivo > 0 && $cantidadProcesada >= $cantidadObjetivo;
        });

        if ($allCompleted) {
            $solicitud->forceFill([
                'estado' => SolicitudCompra::ESTADO_COMPLETADA,
            ])->save();

            return;
        }

        if ((string) $solicitud->estado === SolicitudCompra::ESTADO_COMPLETADA) {
            $solicitud->forceFill([
                'estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA,
            ])->save();
        }
    }
}
