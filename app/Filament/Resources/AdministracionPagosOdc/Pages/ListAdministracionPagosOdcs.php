<?php

namespace App\Filament\Resources\AdministracionPagosOdc\Pages;

use App\Filament\Resources\AdministracionPagosOdc\AdministracionPagosOdcResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAdministracionPagosOdcs extends ListRecords
{
    protected static string $resource = AdministracionPagosOdcResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarListaPagos')
                ->label('Exportar Lista de Pagos')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $templatePath = storage_path('app/templates/LISTA DE PAGOS.xlsx');

                    if (! file_exists($templatePath)) {
                        Notification::make()
                            ->title('Plantilla no encontrada')
                            ->body('No se encontro la plantilla LISTA DE PAGOS.xlsx en storage/app/templates.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    $exportName = 'lista_pagos_' . now()->format('Ymd_His') . '.xlsx';

                    return response()->download($templatePath, $exportName);
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'pagos_pendientes' => Tab::make('Pagos Pendientes')
                ->badge(AdministracionPagosOdcResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PENDIENTE_PAGO_FINANZAS')),
            'pagos_registrados' => Tab::make('Pagos Registrados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $subQuery): void {
                        $subQuery
                            ->whereNotNull('pago_registrado_at')
                            ->orWhereNotNull('comprobante_pago_path');
                    })),
        ];
    }
}
