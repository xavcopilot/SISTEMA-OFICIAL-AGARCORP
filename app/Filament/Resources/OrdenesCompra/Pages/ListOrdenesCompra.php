<?php

namespace App\Filament\Resources\OrdenesCompra\Pages;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Models\OrdenCompra;
use App\Models\Sumario;
use App\Support\SumarioFinanceApprovalService;
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
                        DB::raw('1 as is_sumario_pending_odc_row'),
                    ])),
            'odc_en_correcciones' => Tab::make('ODC en correcciones')
                ->badge(($correctionsCount = $this->odcEnCorreccionesCount()) > 0 ? (string) $correctionsCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $correccionesQuery): Builder {
                        return $correccionesQuery
                            ->where('workflow_post_compra', 'PENDIENTE_APROBACION_GERENCIA_FINANZAS')
                            ->orWhere(function (Builder $rechazadasQuery): Builder {
                                return $rechazadasQuery
                                    ->where('estado', 'RECHAZADA')
                                    ->where('rechazo_etapa', 'gerencia_finanzas');
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
            ->where('estado', 'RECHAZADA')
            ->where('rechazo_etapa', 'gerencia_finanzas')
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
        $totals = [
            1 => 0.0,
            2 => 0.0,
            3 => 0.0,
        ];

        foreach ($sumario->items ?? [] as $item) {
            $selectedOption = $item->opciones->firstWhere('seleccionada', true);
            $selectedProvider = (int) ($selectedOption?->opcion_numero ?? 0);

            if (! in_array($selectedProvider, [1, 2, 3], true)) {
                continue;
            }

            $totals[$selectedProvider] += (float) ($selectedOption?->precio_total ?? 0);
        }

        return $totals;
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
            ->with(['sumario.solicitudCompra', 'items'])
            ->whereNotNull('recepcion_procesada_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        if ($ordenes->isEmpty()) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No hay conformidades registradas aun.</div>';
        }

        $groupedRows = [];

        foreach ($ordenes as $orden) {
            $department = (string) ($orden->sumario?->solicitudCompra?->departamento_solicitante ?: $orden->departamento_solicitante ?: 'SIN DEPARTAMENTO');
            $solicitud = (string) ($orden->sumario?->solicitudCompra?->codigo_control ?: ($orden->sumario?->solicitud_compra_id ?: '-'));

            $items = $orden->items;
            $accepted = (int) $items->where('decision_solicitante', 'ACEPTADO')->count();
            $rejected = (int) $items->where('decision_solicitante', 'RECHAZADO')->count();
            $pending = (int) $items->whereNull('decision_solicitante')->count();
            $total = (int) $items->count();

            if (! isset($groupedRows[$department])) {
                $groupedRows[$department] = [];
            }

            $groupedRows[$department][] = [
                'id' => (string) $orden->getKey(),
                'odc' => (string) ($orden->correlativo_odc ?: ('#' . $orden->id)),
                'solicitud' => $solicitud,
                'workflow' => (string) ($orden->workflow_post_compra ?: '-'),
                'accepted' => $accepted,
                'rejected' => $rejected,
                'pending' => $pending,
                'total' => $total,
            ];
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
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Flujo</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Aceptados</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Rechazados</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Pendientes</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Total items</th>'
                . '<th style="border:1px solid #e5e7eb;padding:8px;">Accion</th>'
                . '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $openOdcButton = sprintf(
                    '<button type="button" wire:click="mountTableAction(\'verResumenOdc\', \'%s\')" style="display:inline-block;border:1px solid #1d4ed8;background:#2563eb;color:#fff;border-radius:6px;padding:5px 9px;text-decoration:none;cursor:pointer;">Abrir ODC</button>',
                    e((string) $row['id'])
                );

                $html .= '<tr>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e($row['odc']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e($row['solicitud']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e(str_replace('_', ' ', $row['workflow'])) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['accepted']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['rejected']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['pending']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['total']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">'
                        . $openOdcButton
                    . '</td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></div></div>';
        }

        $html .= '</div>';

        return $html;
    }
}
