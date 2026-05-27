<?php

namespace App\Filament\Resources\AprobacionOdcs\Pages;

use App\Filament\Resources\AprobacionOdcs\AprobacionOdcResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAprobacionOdcs extends ListRecords
{
    protected static string $resource = AprobacionOdcResource::class;

    public function getTabs(): array
    {
        return [
            'bandeja_aprobacion' => Tab::make('Bandeja de aprobacion')
                ->badge(AprobacionOdcResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PENDIENTE_APROBACION_GERENCIA_FINANZAS')),
            'historial_aprobacion' => Tab::make('Historial de aprobacion')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
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
                    ])),
        ];
    }
}
