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
use Illuminate\Support\HtmlString;

class ListOrdenesCompra extends ListRecords
{
    protected static string $resource = OrdenCompraResource::class;

    public function getTabs(): array
    {
        return [
            'bandeja_gerencia_finanzas' => Tab::make('Bandeja Gerencia Finanzas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PENDIENTE_APROBACION_GERENCIA_FINANZAS')),
            'bandeja_finanzas' => Tab::make('Bandeja Finanzas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PENDIENTE_PAGO_FINANZAS')
                    ->where('estado', 'APROBADA')),
            'facturas_finanzas' => Tab::make('Facturas en Finanzas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('tipo_documento_recepcion', 'FACTURA')
                    ->whereNotNull('factura_path')
                    ->whereNull('factura_enviada_administracion_at')),
            'facturas_enviadas_adm' => Tab::make('Facturas enviadas a Administracion')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('tipo_documento_recepcion', 'FACTURA')
                    ->whereNotNull('factura_path')
                    ->whereNotNull('factura_enviada_administracion_at')),
            'pagadas_transito' => Tab::make('Pagadas y en transito')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PAGADO_Y_EN_TRANSITO')),
            'todas' => Tab::make('Todas'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sumariosPendientesOc')
                ->label('Sumarios pendientes de ODC')
                ->color('info')
                ->modalHeading('Generacion manual por proveedor')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('7xl')
                ->modalContent(fn (): HtmlString => new HtmlString($this->renderPendingSumariosHtml()))
                ->visible(fn (): bool => auth()->user()?->can('GenerateOdcs:Sumario')),
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

    private function renderPendingSumariosHtml(): string
    {
        $sumarios = Sumario::query()
            ->with(['solicitudCompra', 'ordenesCompra'])
            ->whereIn('workflow_estado', ['APROBADO_GERENCIA_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS_PARCIAL'])
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

            $rows .= '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $sumario->correlativo_sdc) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($sumario->solicitudCompra?->codigo_control ?: $sumario->solicitud_compra_id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($sumario->departamento_solicitante ?: '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . $providerButtons . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No hay proveedores pendientes por generar en los sumarios aprobados.</div>';
        }

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Sumario</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Solicitud</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Departamento</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedores ganadores</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>';
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
                'odc' => (string) ($orden->correlativo_odc ?: ('#' . $orden->id)),
                'solicitud' => $solicitud,
                'workflow' => (string) ($orden->workflow_post_compra ?: '-'),
                'accepted' => $accepted,
                'rejected' => $rejected,
                'pending' => $pending,
                'total' => $total,
                'url' => OrdenCompraResource::getUrl('edit', ['record' => $orden]),
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
                $html .= '<tr>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e($row['odc']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e($row['solicitud']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">' . e(str_replace('_', ' ', $row['workflow'])) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['accepted']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['rejected']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['pending']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;text-align:center;">' . e((string) $row['total']) . '</td>'
                    . '<td style="border:1px solid #e5e7eb;padding:8px;">'
                    . '<a href="' . e((string) $row['url']) . '" style="display:inline-block;border:1px solid #1d4ed8;background:#2563eb;color:#fff;border-radius:6px;padding:5px 9px;text-decoration:none;">Abrir ODC</a>'
                    . '</td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></div></div>';
        }

        $html .= '</div>';

        return $html;
    }
}
