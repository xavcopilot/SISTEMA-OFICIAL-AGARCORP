<?php

namespace App\Support;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Models\User;
use App\Support\ControlCodeGenerator;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SumarioFinanceApprovalService
{
    private const SUBESTADO_PENDIENTE_REVALIDACION = 'PENDIENTE_REVALIDACION_GERENCIA';

    /**
     * @return array<int, OrdenCompra>
     */
    public function generateOrdersFromSelections(Sumario $sumario, User $user): array
    {
        return $this->generateOrdersFromSelectionsInternal($sumario, $user, null, null);
    }

    /**
     * @return array<int, OrdenCompra>
     */
    public function generateOrdersForProvider(Sumario $sumario, User $user, ?int $providerId, ?string $providerName): array
    {
        return $this->generateOrdersFromSelectionsInternal($sumario, $user, $providerId, $providerName);
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

    /**
     * @return array<int, OrdenCompra>
     */
    private function generateOrdersFromSelectionsInternal(Sumario $sumario, User $user, ?int $onlyProviderId, ?string $onlyProviderName): array
    {
        return DB::transaction(function () use ($sumario, $user, $onlyProviderId, $onlyProviderName): array {
            $sumario = Sumario::query()
                ->with(['items.opciones', 'items.solicitudCompraItem.solicitudCompra', 'proveedorGanador'])
                ->lockForUpdate()
                ->findOrFail($sumario->id);

            $grouped = [];

            $itemsForOc = $sumario->items
                ->filter(function (SumarioItem $sumarioItem): bool {
                    if ((string) ($sumarioItem->sub_estado ?? '') === self::SUBESTADO_PENDIENTE_REVALIDACION) {
                        return false;
                    }

                    $resultado = (string) ($sumarioItem->validacion_gerencia_resultado ?? '');

                    if ($resultado === '') {
                        return true;
                    }

                    return $resultado === 'CORRECTO';
                })
                ->values();

            foreach ($itemsForOc as $sumarioItem) {
                $selectedOption = $this->resolveSelectedOption($sumario, $sumarioItem);

                if (! $selectedOption) {
                    throw new \RuntimeException('Hay items del sumario sin proveedor seleccionado.');
                }

                $providerId = (int) ($selectedOption->proveedor_id ?? 0);
                $providerName = trim((string) ($selectedOption->proveedor_nombre ?? ''));

                if ($providerId <= 0 && $providerName === '') {
                    throw new \RuntimeException('Hay items seleccionados sin proveedor valido.');
                }

                if ($onlyProviderId) {
                    if ($providerId !== (int) $onlyProviderId) {
                        continue;
                    }
                } elseif (filled($onlyProviderName)) {
                    if (mb_strtolower($providerName) !== mb_strtolower((string) $onlyProviderName)) {
                        continue;
                    }
                }

                $department = trim((string) ($sumarioItem->solicitudCompraItem?->solicitudCompra?->departamento_solicitante ?? $sumario->departamento_solicitante ?? 'SIN_DEPARTAMENTO'));
                $department = $department !== '' ? $department : 'SIN_DEPARTAMENTO';

                $providerGroup = $providerId > 0 ? 'id:' . $providerId : 'name:' . mb_strtolower($providerName);
                $groupKey = $providerGroup . '|dep:' . mb_strtolower($department);

                if (! isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [
                        'provider_id' => $providerId > 0 ? $providerId : null,
                        'provider_name' => $providerName,
                        'departamento_solicitante' => $department,
                        'items' => [],
                    ];
                }

                $grouped[$groupKey]['items'][] = [
                    'sumario_item' => $sumarioItem,
                    'selected_option' => $selectedOption,
                ];
            }

            if ($grouped === []) {
                throw new \RuntimeException('No hay items aprobados por Gerencia para generar ordenes de compra.');
            }

            $createdOrders = [];
            $affectedSolicitudItemIds = [];
            $affectedSumarioItemIds = [];

            foreach ($grouped as $group) {
                $provider = null;
                if (filled($group['provider_id'])) {
                    $provider = Proveedor::query()->find($group['provider_id']);
                }

                $existingOrder = OrdenCompra::query()
                    ->where('sumario_id', $sumario->id)
                    ->where('departamento_solicitante', (string) $group['departamento_solicitante'])
                    ->where(function ($query) use ($group): void {
                        if (filled($group['provider_id'])) {
                            $query->where('proveedor_id', (int) $group['provider_id']);

                            return;
                        }

                        $query->whereHas('items.sumarioItem.opciones', function ($optionQuery) use ($group): void {
                            $optionQuery
                                ->where('seleccionada', true)
                                ->whereRaw('LOWER(proveedor_nombre) = ?', [mb_strtolower((string) $group['provider_name'])]);
                        });
                    })
                    ->first();

                if ($existingOrder) {
                    $createdOrders[] = $existingOrder;

                    continue;
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
                    'departamento_solicitante' => (string) $group['departamento_solicitante'],
                    'sitio_entrega' => null,
                    'comentarios' => (string) ($sumario->observaciones ?? ''),
                    'elaborado_por_user_id' => $user->id,
                    'elaborado_firmado_at' => null,
                    'aprobado_por_user_id' => null,
                    'aprobado_firmado_at' => null,
                    'monto_exento' => $montoExento,
                    'sub_total' => $subTotal,
                    'iva_16' => $iva,
                    'gastos_adicionales' => $gastosAdicionales,
                    'total_general' => round($subTotal + $iva + $gastosAdicionales + $montoExento, 2),
                    'estado' => 'PENDIENTE_APROBACION',
                    'workflow_post_compra' => 'BORRADOR_ODC',
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
                    $affectedSumarioItemIds[] = (int) $sumarioItem->id;
                }

                $createdOrders[] = $ordenCompra;
            }

            if ($affectedSolicitudItemIds !== []) {
                SolicitudItemTrackingService::syncByItemIds(array_values(array_unique($affectedSolicitudItemIds)));
            }

            if ($affectedSumarioItemIds !== []) {
                SumarioItem::query()
                    ->whereIn('id', array_values(array_unique($affectedSumarioItemIds)))
                    ->update(['sub_estado' => 'EN_PROCESO_DE_PAGO']);
            }

            if ($affectedSumarioItemIds !== []) {
                SolicitudCompra::query()
                    ->whereKey($sumario->solicitud_compra_id)
                    ->update(['estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA]);

                $sumario->refresh();
                $sumario->loadMissing(['items.opciones', 'items.solicitudCompraItem.solicitudCompra', 'ordenesCompra']);

                $pendingGroupsCount = $this->pendingProviderGroups($sumario)
                    ->filter(function (array $group) use ($sumario): bool {
                        $query = $sumario->ordenesCompra()->where('departamento_solicitante', (string) $group['departamento_solicitante']);

                        if (filled($group['provider_id'])) {
                            $query->where('proveedor_id', (int) $group['provider_id']);
                        }

                        $query->where(function ($workflowQuery): void {
                            $workflowQuery
                                ->whereNull('workflow_post_compra')
                                ->orWhere('workflow_post_compra', '!=', 'BORRADOR_ODC');
                        });

                        return ! $query->exists();
                    })
                    ->count();

                $sumario->forceFill([
                    'estado' => $pendingGroupsCount > 0 ? 'PENDIENTE_CREACION_ODC' : 'REVISADO_FINANZAS',
                    'revisado_por_user_id' => $user->id,
                    'workflow_estado' => $pendingGroupsCount > 0 ? 'APROBADO_GERENCIA_FINANZAS' : 'ODC_GENERADA',
                ])->save();
            }

            if ($createdOrders !== []) {
                $this->notifyGerenciaFinanzasOdcsPendientes($createdOrders);
            }

            return $createdOrders;
        });
    }

    /**
     * @return Collection<int, array{provider_id:int|null,provider_name:string,provider_key:string,departamento_solicitante:string,total_items:int}>
     */
    public function pendingProviderGroups(Sumario $sumario): Collection
    {
        $sumario = Sumario::query()
            ->with(['items.opciones', 'items.solicitudCompraItem.solicitudCompra'])
            ->findOrFail($sumario->id);

        $groups = collect();

        foreach ($sumario->items as $sumarioItem) {
            if ((string) ($sumarioItem->sub_estado ?? '') === self::SUBESTADO_PENDIENTE_REVALIDACION) {
                continue;
            }

            $resultado = (string) ($sumarioItem->validacion_gerencia_resultado ?? '');
            if ($resultado !== '' && $resultado !== 'CORRECTO') {
                continue;
            }

            $selectedOption = $this->resolveSelectedOption($sumario, $sumarioItem);
            if (! $selectedOption) {
                continue;
            }

            $providerId = (int) ($selectedOption->proveedor_id ?? 0);
            $providerName = trim((string) ($selectedOption->proveedor_nombre ?? ''));
            if ($providerId <= 0 && $providerName === '') {
                continue;
            }

            $department = trim((string) ($sumarioItem->solicitudCompraItem?->solicitudCompra?->departamento_solicitante ?? $sumario->departamento_solicitante ?? 'SIN_DEPARTAMENTO'));
            $department = $department !== '' ? $department : 'SIN_DEPARTAMENTO';

            $providerKey = $providerId > 0 ? 'id:' . $providerId : 'name:' . mb_strtolower($providerName);
            $groupKey = $providerKey . '|dep:' . mb_strtolower($department);

            $existing = $groups->get($groupKey, [
                'provider_id' => $providerId > 0 ? $providerId : null,
                'provider_name' => $providerName,
                'provider_key' => $providerKey,
                'departamento_solicitante' => $department,
                'total_items' => 0,
            ]);

            $existing['total_items']++;
            $groups->put($groupKey, $existing);
        }

        return $groups->values();
    }

    private function nextCorrelativoOdc(): string
    {
        return ControlCodeGenerator::generate('OC', OrdenCompra::class, 'correlativo_odc');
    }

    /**
     * @param  array<int, OrdenCompra>  $orders
     */
    private function notifyGerenciaFinanzasOdcsPendientes(array $orders): void
    {
        $usuarios = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Gerencia de Finanzas'))
            ->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        $correlativos = collect($orders)
            ->pluck('correlativo_odc')
            ->filter()
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();

        $resumen = $correlativos === []
            ? 'Se generaron nuevas ODC pendientes de aprobacion de Gerencia de Finanzas.'
            : 'ODC pendientes de aprobacion: ' . implode(', ', $correlativos) . '.';

        $usuarios->each(function (User $user) use ($resumen): void {
            Notification::make()
                ->title('Nuevas ODC por aprobar')
                ->body($resumen)
                ->warning()
                ->sendToDatabase($user);
        });
    }
}
