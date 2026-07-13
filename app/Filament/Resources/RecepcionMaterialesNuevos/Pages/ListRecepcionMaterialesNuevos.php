<?php

namespace App\Filament\Resources\RecepcionMaterialesNuevos\Pages;

use App\Filament\Resources\RecepcionMaterialesNuevos\RecepcionMaterialesNuevosResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRecepcionMaterialesNuevos extends ListRecords
{
    protected static string $resource = RecepcionMaterialesNuevosResource::class;

    public function getTabs(): array
    {
        $porRecibirCount = RecepcionMaterialesNuevosResource::getEloquentQuery()
            ->where(function (Builder $query): void {
                $query
                    ->where('workflow_post_compra', 'DOCUMENTO_RECEPCION_CARGADO_PROCURA')
                    ->orWhere('workflow_post_compra', 'FACTURA_ENVIADA_ADMINISTRACION')
                    ->orWhere('workflow_post_compra', 'BACKUP_FACTURA_COMPLETADO');
            })
            ->whereNull('recepcion_procesada_at')
            ->count();

        $enTransicionCount = RecepcionMaterialesNuevosResource::getEloquentQuery()
            ->where(function (Builder $query): void {
                $query
                    ->where('workflow_post_compra', 'EN_TRANSICION_ALMACEN')
                    ->orWhere('workflow_post_compra', 'FACTURA_ENVIADA_ADMINISTRACION')
                    ->orWhere('workflow_post_compra', 'BACKUP_FACTURA_COMPLETADO');
            })
            ->whereNotNull('recepcion_procesada_at')
            ->count();

        $pendienteEntradaFinalCount = RecepcionMaterialesNuevosResource::getEloquentQuery()
            ->whereHas('items', fn (Builder $query): Builder => $query
                ->where('decision_solicitante', 'ACEPTADO')
                ->whereNull('procesado_almacen_at'))
            ->count();

        return [
            'por_recibir' => Tab::make('Recibidos en Almacen')
                ->badge($porRecibirCount > 0 ? (string) $porRecibirCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $subQuery): void {
                        $subQuery
                            ->where('workflow_post_compra', 'DOCUMENTO_RECEPCION_CARGADO_PROCURA')
                            ->orWhere('workflow_post_compra', 'FACTURA_ENVIADA_ADMINISTRACION')
                            ->orWhere('workflow_post_compra', 'BACKUP_FACTURA_COMPLETADO');
                    })
                    ->whereNull('recepcion_procesada_at')),
            'en_transicion' => Tab::make('En zona de transicion')
                ->badge($enTransicionCount > 0 ? (string) $enTransicionCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $subQuery): void {
                        $subQuery
                            ->where('workflow_post_compra', 'EN_TRANSICION_ALMACEN')
                            ->orWhere('workflow_post_compra', 'FACTURA_ENVIADA_ADMINISTRACION')
                            ->orWhere('workflow_post_compra', 'BACKUP_FACTURA_COMPLETADO');
                    })
                    ->whereNotNull('recepcion_procesada_at')),
            'pendiente_entrada' => Tab::make('Pendiente de entrada final')
                ->badge($pendienteEntradaFinalCount > 0 ? (string) $pendienteEntradaFinalCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereHas('items', fn (Builder $itemsQuery): Builder => $itemsQuery
                        ->where('decision_solicitante', 'ACEPTADO')
                        ->whereNull('procesado_almacen_at'))),
        ];
    }
}
