<?php

namespace App\Filament\Resources\InspeccionOdcs\Pages;

use App\Filament\Resources\InspeccionOdcs\InspeccionOdcResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInspeccionOdcs extends ListRecords
{
    protected static string $resource = InspeccionOdcResource::class;

    public function getTabs(): array
    {
        return [
            'mis_inspecciones' => Tab::make('Mis Inspecciones')
                ->badge(InspeccionOdcResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PENDIENTE_VALIDACION_FINANZAS')),
            'historial_inspeccion' => Tab::make('Historial')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $historyQuery): void {
                        $historyQuery
                            ->whereIn('workflow_post_compra', [
                                'PENDIENTE_APROBACION_GERENCIA_FINANZAS',
                                'PENDIENTE_PAGO_FINANZAS',
                                'PAGO_REGISTRADO_FINANZAS',
                                'PAGADO_Y_EN_TRANSITO',
                                'DOCUMENTO_RECEPCION_CARGADO_PROCURA',
                                'EN_TRANSICION_ALMACEN',
                                'CONFORMIDAD_POR_ITEMS_COMPLETA',
                                'FACTURA_ENVIADA_ADMINISTRACION',
                                'BACKUP_FACTURA_COMPLETADO',
                                'CERRADA_CONFORME',
                            ])
                            ->orWhere(function (Builder $rejectedQuery): void {
                                $rejectedQuery
                                    ->where('rechazo_etapa', 'validacion_finanzas')
                                    ->whereIn('workflow_post_compra', ['BORRADOR_ODC', 'PENDIENTE_VALIDACION_FINANZAS']);
                            });
                    })),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ayuda_flujo')
                ->label('Flujo')
                ->icon('heroicon-o-information-circle')
                ->color('gray')
                ->modalHeading('Inspeccion de ODC | Flujo')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalDescription('Aqui ves ordenes de compra enviadas para validacion. Puedes revisar y aprobar para enviar a Gerencia de Finanzas o rechazar con motivo para retorno.'),
        ];
    }
}