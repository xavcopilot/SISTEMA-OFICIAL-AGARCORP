<?php

namespace App\Support;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SumarioFinanceApprovalService
{
    /**
     * @return array<int, OrdenCompra>
     */
    public function generateOrdersFromSelections(Sumario $sumario, User $user): array
    {
        return DB::transaction(function () use ($sumario, $user): array {
            $sumario = Sumario::query()
                ->with(['items.opciones', 'proveedorGanador'])
                ->lockForUpdate()
                ->findOrFail($sumario->id);

            $existingOrders = OrdenCompra::query()
                ->where('sumario_id', $sumario->id)
                ->orderBy('id')
                ->get();

            if ($existingOrders->isNotEmpty()) {
                return $existingOrders->all();
            }

            $grouped = [];

            foreach ($sumario->items as $sumarioItem) {
                $selectedOption = $this->resolveSelectedOption($sumario, $sumarioItem);

                if (! $selectedOption) {
                    throw new \RuntimeException('Hay items del sumario sin proveedor seleccionado.');
                }

                $providerId = (int) ($selectedOption->proveedor_id ?? 0);
                $providerName = trim((string) ($selectedOption->proveedor_nombre ?? ''));

                if ($providerId <= 0 && $providerName === '') {
                    throw new \RuntimeException('Hay items seleccionados sin proveedor valido.');
                }

                $groupKey = $providerId > 0 ? 'id:' . $providerId : 'name:' . mb_strtolower($providerName);

                if (! isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [
                        'provider_id' => $providerId > 0 ? $providerId : null,
                        'provider_name' => $providerName,
                        'items' => [],
                    ];
                }

                $grouped[$groupKey]['items'][] = [
                    'sumario_item' => $sumarioItem,
                    'selected_option' => $selectedOption,
                ];
            }

            if ($grouped === []) {
                throw new \RuntimeException('No hay items seleccionados para generar ordenes de compra.');
            }

            $createdOrders = [];
            $affectedSolicitudItemIds = [];

            foreach ($grouped as $group) {
                $provider = null;
                if (filled($group['provider_id'])) {
                    $provider = Proveedor::query()->find($group['provider_id']);
                }

                $subTotal = round(collect($group['items'])->sum(fn (array $entry): float => (float) ($entry['selected_option']->precio_total ?? 0)), 2);
                $iva = round($subTotal * 0.16, 2);
                $gastosAdicionales = 0.0;
                $montoExento = 0.0;

                $ordenCompra = OrdenCompra::query()->create([
                    'sumario_id' => $sumario->id,
                    'correlativo_odc' => $this->nextCorrelativoOdc(),
                    'proveedor_id' => (int) ($group['provider_id'] ?? 0) > 0 ? (int) $group['provider_id'] : null,
                    'rif_proveedor' => (string) ($provider?->rif ?? ''),
                    'direccion_proveedor' => (string) ($provider?->direccion ?? ''),
                    'email_proveedor' => (string) ($provider?->email ?? ''),
                    'contacto_proveedor' => (string) ($provider?->contacto ?? ''),
                    'tasa_bcv' => null,
                    'condicion_pago' => $sumario->condiciones_pago,
                    'monto_exento' => $montoExento,
                    'sub_total' => $subTotal,
                    'iva_16' => $iva,
                    'gastos_adicionales' => $gastosAdicionales,
                    'total_general' => round($subTotal + $iva + $gastosAdicionales + $montoExento, 2),
                    'estado' => 'PENDIENTE_APROBACION',
                    'workflow_post_compra' => 'PENDIENTE_PAGO_FINANZAS',
                ]);

                foreach ($group['items'] as $entry) {
                    /** @var SumarioItem $sumarioItem */
                    $sumarioItem = $entry['sumario_item'];
                    /** @var SumarioItemOpcion $selectedOption */
                    $selectedOption = $entry['selected_option'];

                    OrdenCompraItem::query()->create([
                        'orden_compra_id' => $ordenCompra->id,
                        'sumario_item_id' => $sumarioItem->id,
                        'solicitud_compra_item_id' => $sumarioItem->solicitud_compra_item_id,
                        'item' => $sumarioItem->item,
                        'descripcion' => $sumarioItem->descripcion,
                        'unidad_medida' => $sumarioItem->unidad_medida,
                        'cantidad' => $sumarioItem->cantidad,
                        'precio_unitario' => round((float) ($selectedOption->precio_unitario ?? 0), 2),
                        'precio_total' => round((float) ($selectedOption->precio_total ?? 0), 2),
                    ]);

                    $affectedSolicitudItemIds[] = (int) $sumarioItem->solicitud_compra_item_id;
                }

                $createdOrders[] = $ordenCompra;
            }

            if ($affectedSolicitudItemIds !== []) {
                SolicitudCompraItem::query()
                    ->whereIn('id', array_values(array_unique($affectedSolicitudItemIds)))
                    ->update(['estado_item' => 'EN_OC']);
            }

            SolicitudCompra::query()
                ->whereKey($sumario->solicitud_compra_id)
                ->update(['estado' => 'OC_PENDIENTE_APROBACION']);

            $sumario->forceFill([
                'estado' => 'REVISADO_FINANZAS',
                'revisado_por_user_id' => $user->id,
                'workflow_estado' => 'ODC_GENERADA',
            ])->save();

            return $createdOrders;
        });
    }

    public function approveByFinance(Sumario $sumario, User $user): OrdenCompra
    {
        $orders = $this->generateOrdersFromSelections($sumario, $user);

        if ($orders === []) {
            throw new \RuntimeException('No se pudo generar ninguna orden de compra.');
        }

        return $orders[0];
    }

    private function resolveSelectedOption(Sumario $sumario, SumarioItem $item): ?SumarioItemOpcion
    {
        $selected = $item->opciones->firstWhere('seleccionada', true);

        if ($selected) {
            return $selected;
        }

        $winnerProviderId = (int) ($sumario->proveedor_ganador_id ?? 0);
        if ($winnerProviderId > 0) {
            $legacy = $item->opciones->first(fn (SumarioItemOpcion $option): bool => (int) ($option->proveedor_id ?? 0) === $winnerProviderId);
            if ($legacy) {
                return $legacy;
            }
        }

        return $item->opciones
            ->sortBy(fn (SumarioItemOpcion $option): float => (float) ($option->precio_total ?? 0))
            ->first();
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
