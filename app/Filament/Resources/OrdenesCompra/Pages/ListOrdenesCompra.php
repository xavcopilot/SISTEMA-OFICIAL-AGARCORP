<?php

namespace App\Filament\Resources\OrdenesCompra\Pages;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Models\Sumario;
use App\Support\SumarioFinanceApprovalService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListOrdenesCompra extends ListRecords
{
    protected static string $resource = OrdenCompraResource::class;

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
}
