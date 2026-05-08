<?php

namespace App\Filament\Resources\OrdenesCompra\Pages;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Sumario;
use App\Support\SumarioFinanceApprovalService;
use App\Support\SumarioProviderGrouping;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListOrdenesCompra extends ListRecords
{
    protected static string $resource = OrdenCompraResource::class;

    public function getTabs(): array
    {
        $pendingSumarioIds = $this->pendingSumarioIdsWithPendingProviders();

        return [
            'creacion_odc' => Tab::make('Creacion de ODC')
                ->badge(($creationCount = count($pendingSumarioIds)) > 0 ? (string) $creationCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->from('sumarios as ordenes_compra')
                    ->leftJoin('solicitud_compras', 'solicitud_compras.id', '=', 'ordenes_compra.solicitud_compra_id')
                    ->leftJoin('users as solicitantes', 'solicitantes.id', '=', 'solicitud_compras.solicitado_por_user_id')
                    ->where('ordenes_compra.workflow_estado', 'APROBADO_GERENCIA_FINANZAS')
                    ->when($pendingSumarioIds === [], fn (Builder $subQuery): Builder => $subQuery->whereRaw('1 = 0'))
                    ->when($pendingSumarioIds !== [], fn (Builder $subQuery): Builder => $subQuery->whereIn('ordenes_compra.id', $pendingSumarioIds))
                    ->select([
                        'ordenes_compra.id',
                        'ordenes_compra.created_at',
                        'ordenes_compra.updated_at',
                        'ordenes_compra.correlativo_sdc',
                        'ordenes_compra.fecha',
                        'ordenes_compra.solicitud_compra_id',
                        'ordenes_compra.procedencia',
                        'ordenes_compra.tipo_orden',
                        'ordenes_compra.workflow_estado',
                        'ordenes_compra.estado',
                        DB::raw('solicitud_compras.codigo_control as solicitud_codigo_control'),
                        DB::raw("COALESCE(solicitantes.name, '-') as solicitante_nombre"),
                        DB::raw('1 as is_sumario_pending_odc_row'),
                    ])),
            'odc_en_correcciones' => Tab::make('ODC en correcciones')
                ->badge(($correctionsCount = $this->odcEnCorreccionesCount()) > 0 ? (string) $correctionsCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $correccionesQuery): Builder {
                        return $correccionesQuery
                            ->where('workflow_post_compra', 'PENDIENTE_VALIDACION_FINANZAS')
                            ->orWhere('workflow_post_compra', 'PENDIENTE_APROBACION_GERENCIA_FINANZAS')
                            ->orWhere(function (Builder $rechazadasQuery): Builder {
                                return $rechazadasQuery
                                    ->where('estado', 'RECHAZADA')
                                    ->whereIn('rechazo_etapa', ['gerencia_finanzas', 'validacion_finanzas']);
                            });
                    })),
            'pagos_odc' => Tab::make('Pagos de ODC')
                ->badge(($pagosCount = $this->pagosOdcCount()) > 0 ? (string) $pagosCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PAGO_REGISTRADO_FINANZAS')),
            'historial_odc' => Tab::make('Historial de ODC')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $historyQuery): Builder {
                        return $historyQuery
                            ->where(function (Builder $approvedQuery): Builder {
                                return $approvedQuery
                                    ->whereIn('workflow_post_compra', [
                                        'PENDIENTE_PAGO_FINANZAS',
                                        'PAGO_REGISTRADO_FINANZAS',
                                        'PAGADO_Y_EN_TRANSITO',
                                        'DOCUMENTO_RECEPCION_CARGADO_PROCURA',
                                        'EN_TRANSICION_ALMACEN',
                                        'CONFORMIDAD_POR_ITEMS_COMPLETA',
                                        'FACTURA_ENVIADA_ADMINISTRACION',
                                        'BACKUP_FACTURA_COMPLETADO',
                                        'CERRADA_CONFORME',
                                    ]);
                            })
                            ->orWhere(function (Builder $rejectedHistoryQuery): Builder {
                                return $rejectedHistoryQuery
                                    ->where('estado', 'RECHAZADA')
                                    ->where('rechazo_etapa', 'historial');
                            });
                    })),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('conformidadesUsuarios')
                ->label('Conformidades de Usuarios')
                ->color('warning')
                ->modalHeading('Conformidades agrupadas por departamento')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('7xl')
                ->modalContent(fn (): HtmlString => new HtmlString($this->renderConformidadesUsuariosHtml()))
                ->visible(fn (): bool => auth()->user()?->can('ViewAny:OrdenCompra')),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        if (blank($this->activeTab)) {
            $this->activeTab = 'creacion_odc';
        }

        if (session()->has('status')) {
            Notification::make()
                ->title((string) session('status'))
                ->success()
                ->send();
        }

        if (session()->has('error')) {
            Notification::make()
                ->title('No se pudo generar ODC')
                ->body((string) session('error'))
                ->danger()
                ->send();
        }
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
        $this->cachedDefaultTableColumnState = null;
        $this->applyTableColumnManager();
    }

    public function marcarDevolucionPlanificada(string $ordenId): void
    {
        $ordenCompra = OrdenCompra::query()
            ->with('items:id,orden_compra_id,decision_solicitante')
            ->find((int) $ordenId);

        if (! $ordenCompra) {
            Notification::make()
                ->title('ODC no encontrada')
                ->danger()
                ->send();

            return;
        }

        if (! $ordenCompra->items->contains(fn (OrdenCompraItem $item): bool => (string) $item->decision_solicitante === 'RECHAZADO')) {
            Notification::make()
                ->title('Sin rechazos para planificar')
                ->body('Esta ODC no tiene items rechazados por el solicitante.')
                ->warning()
                ->send();

            return;
        }

        $ordenCompra->forceFill([
            'workflow_post_compra' => 'DEVOLUCION_PLANIFICADA',
            'devolucion_motivo' => $ordenCompra->devolucion_motivo ?: 'Devolucion planificada con proveedor por rechazo del solicitante.',
        ])->save();

        Notification::make()
            ->title('Devolucion planificada')
            ->body('La ODC quedo marcada como devolucion planificada con proveedor.')
            ->success()
            ->send();
    }

    public function marcarDevolucionRealizada(string $ordenId): void
    {
        $ordenCompra = OrdenCompra::query()
            ->with('items')
            ->find((int) $ordenId);

        if (! $ordenCompra) {
            Notification::make()
                ->title('ODC no encontrada')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($ordenCompra): void {
            OrdenCompraItem::query()
                ->where('orden_compra_id', (int) $ordenCompra->id)
                ->where('decision_solicitante', 'RECHAZADO')
                ->update([
                    'decision_solicitante' => null,
                    'motivo_rechazo_solicitante' => null,
                    'conformidad_solicitante_at' => null,
                    'estado_recepcion' => 'ZONA_TRANSICION',
                    'entregado_at' => null,
                ]);

            $ordenCompra->forceFill([
                'workflow_post_compra' => 'DEVOLUCION_REALIZADA',
                'conformidad_solicitante_at' => null,
                'conformidad_por_user_id' => null,
            ])->save();
        });

        Notification::make()
            ->title('Devolucion realizada')
            ->body('Los items rechazados quedaron listos para una nueva conformidad del solicitante.')
            ->success()
            ->send();
    }

    public function marcarOdcResuelta(string $ordenId): void
    {
        $ordenCompra = OrdenCompra::query()
            ->with('items:id,orden_compra_id,decision_solicitante')
            ->find((int) $ordenId);

        if (! $ordenCompra) {
            Notification::make()
                ->title('ODC no encontrada')
                ->danger()
                ->send();

            return;
        }

        $items = $ordenCompra->items;
        $total = (int) $items->count();
        $rejected = (int) $items->where('decision_solicitante', 'RECHAZADO')->count();
        $pending = (int) $items
            ->filter(function ($item): bool {
                return strtoupper((string) ($item->decision_solicitante ?? '')) !== 'ACEPTADO';
            })
            ->count();

        if ($total === 0 || $rejected > 0 || $pending > 0) {
            Notification::make()
                ->title('La ODC aun no puede cerrarse')
                ->body('Solo puedes marcarla como resuelta cuando no queden pendientes ni rechazos activos en esta ODC.')
                ->warning()
                ->send();

            return;
        }

        $ordenCompra->forceFill([
            'workflow_post_compra' => 'CERRADA_CONFORME',
        ])->save();

        Notification::make()
            ->title('ODC resuelta')
            ->body('La ODC fue marcada como resuelta y dejara de mostrarse en este listado.')
            ->success()
            ->send();
    }

    protected function getTablePollingInterval(): ?string
    {
        return $this->resolveActiveTab() === 'odc_en_correcciones' ? '2s' : null;
    }

    private function renderPendingSumariosHtml(): string
    {
        $sumarios = Sumario::query()
            ->with(['solicitudCompra', 'ordenesCompra', 'items.opciones'])
            ->whereIn('workflow_estado', ['APROBADO_GERENCIA_FINANZAS'])
            ->orderByDesc('id')
            ->get();

        if ($sumarios->isEmpty()) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No hay sumarios pendientes de generacion de ODC.</div>';
        }

        $rows = '';
        $service = app(SumarioFinanceApprovalService::class);

        foreach ($sumarios as $sumario) {
            $groups = $service->pendingProviderGroups($sumario)
                ->filter(fn (array $group): bool => ! $service->hasExistingGeneratedOrderForGroup($sumario, $group))
                ->values();

            if ($groups->isEmpty()) {
                continue;
            }

            $providerButtons = '';

            foreach ($groups as $group) {
                $providerName = e((string) ($group['provider_name'] ?: ('Proveedor #' . ($group['provider_id'] ?? 'N/A'))));
                $department = e((string) $group['departamento_solicitante']);
                $totalItems = (int) ($group['total_items'] ?? 0);

                $form = '<form method="POST" action="' . e(route('ordenes-compra.generar-desde-sumario', ['sumario' => $sumario->id])) . '" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
                    . '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">'
                    . '<input type="hidden" name="provider_id" value="' . e((string) ($group['provider_id'] ?? '')) . '">'
                    . '<input type="hidden" name="provider_name" value="' . e((string) ($group['provider_name'] ?? '')) . '">'
                    . '<span style="font-size:12px;color:#111827;">' . $providerName . ' | Dep: ' . $department . ' | Items: ' . $totalItems . '</span>'
                    . '<button type="submit" style="border:1px solid #1d4ed8;background:#2563eb;color:white;border-radius:6px;padding:6px 10px;font-size:12px;">Crear OC para este Proveedor</button>'
                    . '</form>';

                $providerButtons .= '<div style="margin-bottom:8px;">' . $form . '</div>';
            }

            $providerTotals = $this->resolveSelectedProviderTotals($sumario);
            $dynamicMessage = $this->buildPendingOdcMessage($groups);

            $rows .= '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $sumario->correlativo_sdc) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) optional($sumario->fecha)->format('d/m/Y')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($sumario->solicitudCompra?->codigo_control ?: $sumario->solicitud_compra_id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($sumario->procedencia ?: '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($sumario->tipo_orden ?: '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e($dynamicMessage) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e($this->humanReadableWorkflowState((string) ($sumario->workflow_estado ?: $sumario->estado))) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">'
                . '<div style="margin-bottom:8px;font-weight:600;">Faltan ' . e((string) $groups->count()) . '</div>'
                . $providerButtons
                . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($providerTotals[1] ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($providerTotals[2] ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($providerTotals[3] ?? 0), 2, ',', '.') . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No hay proveedores pendientes por generar en los sumarios aprobados.</div>';
        }

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Correlativo SDC</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Fecha</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Solicitud</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Procedencia</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Tipo orden</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Mensaje dinamico</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">ODC faltantes</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Total Prov. 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Total Prov. 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Total Prov. 3</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>';
    }

    private function buildPendingOdcMessage($groups): string
    {
        if (! method_exists($groups, 'isEmpty') || $groups->isEmpty()) {
            return 'Sin items';
        }

        $providerNames = $groups
            ->map(fn (array $group): string => trim((string) ($group['provider_name'] ?? '')))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values();

        if ($providerNames->isEmpty()) {
            return 'Pendiente por generar ODC';
        }

        return 'Pendiente con: ' . $providerNames->implode(', ');
    }

    private function pendingSumariosCount(): int
    {
        return count($this->pendingSumarioIdsWithPendingProviders());
    }

    private function odcEnCorreccionesCount(): int
    {
        return OrdenCompra::query()
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereIn('workflow_post_compra', [
                        'PENDIENTE_VALIDACION_FINANZAS',
                        'PENDIENTE_APROBACION_GERENCIA_FINANZAS',
                    ])
                    ->orWhere(function (Builder $rechazadasQuery): Builder {
                        return $rechazadasQuery
                            ->where('estado', 'RECHAZADA')
                            ->whereIn('rechazo_etapa', ['gerencia_finanzas', 'validacion_finanzas']);
                    });
            })
            ->count();
    }

    private function pagosOdcCount(): int
    {
        return OrdenCompra::query()
            ->where('workflow_post_compra', 'PAGO_REGISTRADO_FINANZAS')
            ->count();
    }

    /**
     * @return array<int, int>
     */
    private function pendingSumarioIdsWithPendingProviders(): array
    {
        $sumarios = Sumario::query()
            ->with(['ordenesCompra', 'items.opciones', 'items.solicitudCompraItem.solicitudCompra'])
            ->where('workflow_estado', 'APROBADO_GERENCIA_FINANZAS')
            ->orderByDesc('id')
            ->get();

        if ($sumarios->isEmpty()) {
            return [];
        }

        $service = app(SumarioFinanceApprovalService::class);

        return $sumarios
            ->filter(function (Sumario $sumario) use ($service): bool {
                $groups = $service->pendingProviderGroups($sumario)
                    ->filter(fn (array $group): bool => ! $service->hasExistingGeneratedOrderForGroup($sumario, $group))
                    ->values();

                return $groups->isNotEmpty();
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function resolveSelectedProviderTotals(Sumario $sumario): array
    {
        return SumarioProviderGrouping::groupedTotalsFromSumario($sumario);
    }

    private function humanReadableWorkflowState(string $state): string
    {
        return match ($state) {
            'VALIDADO_FINANZAS' => 'EN ESPERA DE APROBACION GERENCIA',
            'APROBADO_GERENCIA_FINANZAS' => 'PENDIENTE POR ORDENES DE COMPRA',
            default => str_replace('_', ' ', $state),
        };
    }

    private function isCreationOdcTab(): bool
    {
        return $this->resolveActiveTab() === 'creacion_odc';
    }

    private function resolveActiveTab(): string
    {
        if (method_exists($this, 'getActiveTab')) {
            return (string) ($this->getActiveTab() ?: 'creacion_odc');
        }

        return (string) ($this->activeTab ?: 'creacion_odc');
    }

    private function renderConformidadesUsuariosHtml(): string
    {
        $ordenes = OrdenCompra::query()
            ->with(['sumario.solicitudCompra.solicitadoPor', 'proveedor', 'items'])
            ->whereNotNull('recepcion_procesada_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('workflow_post_compra')
                    ->orWhere('workflow_post_compra', '!=', 'CERRADA_CONFORME');
            })
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        if ($ordenes->isEmpty()) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No hay conformidades registradas aun.</div>';
        }

        $rejectedSolicitudItemIds = $ordenes
            ->flatMap(fn (OrdenCompra $orden) => $orden->items
                ->where('decision_solicitante', 'RECHAZADO')
                ->pluck('solicitud_compra_item_id'))
            ->filter()
            ->unique()
            ->values();

        $attendedSolicitudItemIds = OrdenCompraItem::query()
            ->when(
                $rejectedSolicitudItemIds->isNotEmpty(),
                fn ($query) => $query->whereIn('solicitud_compra_item_id', $rejectedSolicitudItemIds),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->where(function ($query): void {
                $query
                    ->where('decision_solicitante', 'ACEPTADO')
                    ->orWhereNotNull('procesado_almacen_at')
                    ->orWhere('estado_recepcion', 'ENTREGADO_SOLICITANTE');
            })
            ->pluck('solicitud_compra_item_id')
            ->filter()
            ->unique()
            ->flip();

        $groupedRows = [];

        foreach ($ordenes as $orden) {
            $solicitudModel = $orden->sumario?->solicitudCompra;

            $department = (string) ($solicitudModel?->departamento_solicitante ?: $orden->departamento_solicitante ?: 'SIN DEPARTAMENTO');
            $solicitud = (string) ($solicitudModel?->codigo_control ?: ($orden->sumario?->solicitud_compra_id ?: '-'));
            $solicitante = (string) ($solicitudModel?->solicitadoPor?->name ?: '-');

            $items = $orden->items;
            $accepted = (int) $items->where('decision_solicitante', 'ACEPTADO')->count();
            $rejected = (int) $items->where('decision_solicitante', 'RECHAZADO')->count();
            $total = (int) $items->count();
            $workflowRaw = strtoupper((string) ($orden->workflow_post_compra ?? ''));
            $pending = (int) $items
                ->filter(function ($item) use ($workflowRaw): bool {
                    $decision = strtoupper((string) ($item->decision_solicitante ?? ''));

                    if ($decision === '') {
                        return true;
                    }

                    if ($decision === 'ACEPTADO') {
                        return false;
                    }

                    if ($decision !== 'RECHAZADO') {
                        return true;
                    }

                    $returnClosed = $workflowRaw === 'DEVOLUCION_REALIZADA';

                    return ! $returnClosed;
                })
                ->count();

            $providerName = (string) ($orden->proveedor?->nombre ?: 'No definido');
            $providerContact = (string) ($orden->proveedor?->contacto ?: $orden->contacto_proveedor ?: 'No definido');
            $providerPhone = (string) ($orden->proveedor?->telefono ?: 'No definido');

            $rejectedItems = $items
                ->where('decision_solicitante', 'RECHAZADO')
                ->values()
                ->map(function ($item) use ($attendedSolicitudItemIds, $orden): array {
                    $solicitudItemId = (int) ($item->solicitud_compra_item_id ?? 0);
                    $isPlanned = strtoupper((string) ($orden->workflow_post_compra ?? '')) === 'DEVOLUCION_PLANIFICADA';
                    $attention = $isPlanned
                        ? 'DEVOLUCION PLANIFICADA'
                        : ($solicitudItemId > 0 && isset($attendedSolicitudItemIds[$solicitudItemId])
                            ? 'RECHAZO ATENDIDO'
                            : 'PENDIENTE POR ATENDER');

                    return [
                        'item' => (string) ($item->item ?: ('#' . $item->id)),
                        'descripcion' => (string) ($item->descripcion ?: '-'),
                        'motivo' => (string) ($item->motivo_rechazo_solicitante ?: 'Sin motivo registrado'),
                        'estado_atencion' => $attention,
                    ];
                })
                ->all();

            $printUrl = route('ordenes-compra.formato.print', ['ordenCompra' => $orden]);

            if (! isset($groupedRows[$department])) {
                $groupedRows[$department] = [];
            }

            $groupedRows[$department][] = [
                'id' => (string) $orden->getKey(),
                'odc' => (string) ($orden->correlativo_odc ?: ('#' . $orden->id)),
                'solicitud' => $solicitud,
                'solicitante' => $solicitante,
                'workflow_raw' => (string) ($orden->workflow_post_compra ?? ''),
                'workflow' => self::humanReadableOdcFlow((string) ($orden->workflow_post_compra ?: '-')),
                'accepted' => $accepted,
                'rejected' => $rejected,
                'pending' => $pending,
                'total' => $total,
                'provider_name' => $providerName,
                'provider_contact' => $providerContact,
                'provider_phone' => $providerPhone,
                'details' => $rejectedItems,
                'print_url' => $printUrl,
            ];
        }

        if ($groupedRows === []) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No hay conformidades pendientes por gestionar.</div>';
        }

        ksort($groupedRows);

        $html = '<div style="display:flex;flex-direction:column;gap:16px;">';

        foreach ($groupedRows as $department => $rows) {
            $html .= '<div style="border:1px solid #d1d5db;border-radius:8px;overflow:hidden;">';
            $html .= '<div style="padding:10px 12px;background:#f3f4f6;font-weight:600;">Departamento: ' . e((string) $department) . '</div>';
            $html .= '<div style="overflow:auto;">';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
            $html .= '<thead><tr style="background:#fafafa;">'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">ODC</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Solicitud</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Solicitante</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Flujo</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Aceptados</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Rechazados</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Pendientes por resolver en esta ODC</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Total items OC</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Detalles</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Accion</th>'
                . '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $detailItems = collect($row['details'] ?? [])
                    ->map(function (array $detail) use ($row): string {
                        return '<li style="margin-bottom:8px;">'
                            . '<div><strong>Item:</strong> ' . e((string) ($detail['item'] ?? '-')) . '</div>'
                            . '<div><strong>Descripcion:</strong> ' . e((string) ($detail['descripcion'] ?? '-')) . '</div>'
                            . '<div><strong>Motivo rechazo:</strong> ' . e((string) ($detail['motivo'] ?? '-')) . '</div>'
                                . '<div><strong>Proveedor:</strong> ' . e((string) ($row['provider_name'] ?? '-')) . '</div>'
                                . '<div><strong>Contacto:</strong> ' . e((string) ($row['provider_contact'] ?? '-')) . '</div>'
                                . '<div><strong>Telefono:</strong> ' . e((string) ($row['provider_phone'] ?? '-')) . '</div>'
                            . '<div><strong>Atencion:</strong> ' . e((string) ($detail['estado_atencion'] ?? '-')) . '</div>'
                            . '</li>';
                    })
                    ->implode('');

                $detailsHtml = $detailItems === ''
                    ? '<span style="color:#6b7280;">Sin rechazos</span>'
                    : '<details>'
                        . '<summary style="cursor:pointer;color:#1d4ed8;">Ver detalles</summary>'
                        . '<ul style="margin:8px 0 0 16px;padding:0;">' . $detailItems . '</ul>'
                    . '</details>';

                $openOdcButton = '<a href="' . e((string) $row['print_url']) . '" target="_blank" rel="noopener" style="display:inline-block;border:1px solid #1d4ed8;background:#2563eb;color:#fff;border-radius:6px;padding:5px 9px;text-decoration:none;">Vista imprimir</a>';
                $workflowRaw = strtoupper((string) ($row['workflow_raw'] ?? ''));
                $planActionButton = (int) ($row['rejected'] ?? 0) > 0 && $workflowRaw !== 'DEVOLUCION_PLANIFICADA' && $workflowRaw !== 'DEVOLUCION_REALIZADA'
                    ? '<button type="button" wire:click="marcarDevolucionPlanificada(\'' . e((string) $row['id']) . '\')" style="display:inline-block;border:1px solid #a16207;background:#ca8a04;color:#fff;border-radius:6px;padding:5px 9px;cursor:pointer;">Marcar devolucion planificada</button>'
                    : '';
                $reopenConformidadButton = $workflowRaw === 'DEVOLUCION_PLANIFICADA'
                    ? '<button type="button" wire:click="marcarDevolucionRealizada(\'' . e((string) $row['id']) . '\')" style="display:inline-block;border:1px solid #0f766e;background:#0d9488;color:#fff;border-radius:6px;padding:5px 9px;cursor:pointer;">Marcar devolucion realizada</button>'
                    : '';
                $resolveOdcButton = (int) ($row['pending'] ?? 0) === 0
                    && (int) ($row['rejected'] ?? 0) === 0
                    && $workflowRaw !== 'CERRADA_CONFORME'
                    ? '<div x-data="{ show: false }">
                        <button type="button" @click="show = true" style="display:inline-block;border:1px solid #166534;background:#16a34a;color:#fff;border-radius:6px;padding:5px 9px;cursor:pointer;">ODC Resuelta</button>
                        <div x-show="show" x-cloak style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:99999;background:rgba(0,0,0,0.5);">
                            <div @click.away="show = false" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;border-radius:8px;padding:24px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                                <h3 style="margin:0 0 8px;font-size:16px;font-weight:600;">Confirmar ODC resuelta</h3>
                                <p style="margin:0 0 20px;color:#6b7280;font-size:14px;">Esta ODC ya quedo totalmente resuelta. Deseas marcarla como resuelta y ocultarla de este listado?</p>
                                <div style="display:flex;gap:8px;justify-content:flex-end;">
                                    <button type="button" @click="show = false" style="border:1px solid #d1d5db;background:white;color:#374151;border-radius:6px;padding:8px 16px;cursor:pointer;font-size:14px;">Cancelar</button>
                                    <button type="button" @click="show = false; $wire.marcarOdcResuelta(\'' . e((string) $row['id']) . '\')" style="border:1px solid #166534;background:#16a34a;color:white;border-radius:6px;padding:8px 16px;cursor:pointer;font-size:14px;">Confirmar</button>
                                </div>
                            </div>
                        </div>
                    </div>'
                    : '';
                $actionButtons = '<div style="display:flex;flex-wrap:wrap;gap:6px;">' . $openOdcButton . $planActionButton . $reopenConformidadButton . $resolveOdcButton . '</div>';

                $html .= '<tr>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e($row['odc']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e($row['solicitud']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e((string) $row['solicitante']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e((string) $row['workflow']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['accepted']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['rejected']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['pending']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['total']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . $detailsHtml . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">'
                        . $actionButtons
                    . '</td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></div></div>';
        }

        $html .= '</div>';

        return $html;
    }

    private static function humanReadableOdcFlow(string $workflow): string
    {
        $normalized = strtoupper(trim($workflow));

        return match ($normalized) {
            'DOCUMENTO_RECEPCION_CARGADO_PROCURA' => 'DOCUMENTO CARGADO POR PROCURA',
            'EN_TRANSICION_ALMACEN' => 'EN TRANSICION A ALMACEN',
            'CONFORMIDAD_POR_ITEMS_COMPLETA' => 'CONFORMIDAD COMPLETA',
            'RECHAZADA_SOLICITANTE', 'RECHAZO_SOLICITANTE' => 'RECHAZADA POR SOLICITANTE',
            'DEVOLUCION_PLANIFICADA' => 'DEVOLUCION PLANIFICADA',
            'DEVOLUCION_REALIZADA' => 'DEVOLUCION REALIZADA',
            'CERRADA_CONFORME' => 'CERRADA CONFORME',
            '' => '-',
            default => str_replace('_', ' ', $normalized),
        };
    }
}
