<?php

namespace App\Support;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SumarioFinanceApprovalService
{
    public function approveByFinance(Sumario $sumario, User $user): OrdenCompra
    {
        return DB::transaction(function () use ($sumario, $user): OrdenCompra {
            $sumario = Sumario::query()
                ->with(['items.opciones', 'proveedorGanador'])
                ->lockForUpdate()
                ->findOrFail($sumario->id);

            $existingOrder = OrdenCompra::query()->where('sumario_id', $sumario->id)->first();
            if ($existingOrder) {
                $this->markAsReviewed($sumario, $user);

                return $existingOrder;
            }

            if ($sumario->proveedor_ganador_id === null) {
                throw new \RuntimeException('El sumario no tiene proveedor ganador seleccionado.');
            }

            $winnerProviderId = (int) $sumario->proveedor_ganador_id;
            $winnerColumn = $this->resolveWinnerColumn($sumario, $winnerProviderId);

            if ($winnerColumn === null) {
                throw new \RuntimeException('No se pudo identificar la columna del proveedor ganador.');
            }

            $proveedor = $sumario->proveedorGanador;

            $subTotal = 0.0;
            foreach ($sumario->items as $sumarioItem) {
                $opcion = $sumarioItem->opciones->firstWhere('opcion_numero', $winnerColumn);
                $subTotal += (float) ($opcion?->precio_total ?? 0);
            }

            $subTotal = round($subTotal, 2);
            $iva = round($subTotal * 0.16, 2);
            $gastosAdicionales = 0.0;
            $montoExento = 0.0;

            $ordenCompra = OrdenCompra::query()->create([
                'sumario_id' => $sumario->id,
                'correlativo_odc' => $this->nextCorrelativoOdc(),
                'proveedor_id' => $winnerProviderId,
                'rif_proveedor' => (string) ($proveedor?->rif ?? ''),
                'direccion_proveedor' => (string) ($proveedor?->direccion ?? ''),
                'email_proveedor' => (string) ($proveedor?->email ?? ''),
                'contacto_proveedor' => (string) ($proveedor?->contacto ?? ''),
                'tasa_bcv' => null,
                'condicion_pago' => $sumario->condiciones_pago,
                'monto_exento' => $montoExento,
                'sub_total' => $subTotal,
                'iva_16' => $iva,
                'gastos_adicionales' => $gastosAdicionales,
                'total_general' => round($subTotal + $iva + $gastosAdicionales + $montoExento, 2),
                'estado' => 'PENDIENTE_APROBACION',
            ]);

            $affectedSolicitudItemIds = [];

            foreach ($sumario->items as $sumarioItem) {
                $opcionGanadora = $sumarioItem->opciones->firstWhere('opcion_numero', $winnerColumn);
                $precioUnitario = (float) ($opcionGanadora?->precio_unitario ?? 0);
                $precioTotal = (float) ($opcionGanadora?->precio_total ?? 0);

                OrdenCompraItem::query()->create([
                    'orden_compra_id' => $ordenCompra->id,
                    'sumario_item_id' => $sumarioItem->id,
                    'solicitud_compra_item_id' => $sumarioItem->solicitud_compra_item_id,
                    'item' => $sumarioItem->item,
                    'descripcion' => $sumarioItem->descripcion,
                    'unidad_medida' => $sumarioItem->unidad_medida,
                    'cantidad' => $sumarioItem->cantidad,
                    'precio_unitario' => round($precioUnitario, 2),
                    'precio_total' => round($precioTotal, 2),
                ]);

                $affectedSolicitudItemIds[] = (int) $sumarioItem->solicitud_compra_item_id;
            }

            if ($affectedSolicitudItemIds !== []) {
                SolicitudCompraItem::query()
                    ->whereIn('id', $affectedSolicitudItemIds)
                    ->update(['estado_item' => 'EN_OC']);
            }

            SolicitudCompra::query()
                ->whereKey($sumario->solicitud_compra_id)
                ->update(['estado' => 'OC_PENDIENTE_APROBACION']);

            $this->markAsReviewed($sumario, $user);

            return $ordenCompra;
        });
    }

    private function markAsReviewed(Sumario $sumario, User $user): void
    {
        $sumario->forceFill([
            'estado' => 'REVISADO_FINANZAS',
            'revisado_por_user_id' => $user->id,
        ])->save();
    }

    private function resolveWinnerColumn(Sumario $sumario, int $winnerProviderId): ?int
    {
        $firstItem = $sumario->items->first();

        if (! $firstItem) {
            return null;
        }

        foreach ([1, 2, 3] as $column) {
            $opcion = $firstItem->opciones->firstWhere('opcion_numero', $column);

            if ((int) ($opcion?->proveedor_id ?? 0) === $winnerProviderId) {
                return $column;
            }
        }

        return null;
    }

    private function nextCorrelativoOdc(): string
    {
        $year = now()->format('Y');

        $next = OrdenCompra::query()
            ->where('correlativo_odc', 'like', 'ODC-%-' . $year)
            ->count() + 1;

        do {
            $correlativo = sprintf('ODC-%03d-%s', $next, $year);
            $exists = OrdenCompra::query()->where('correlativo_odc', $correlativo)->exists();
            $next++;
        } while ($exists);

        return $correlativo;
    }
}
