<?php

namespace App\Filament\Resources\RecepcionNuevosMateriales\Pages;

use App\Filament\Resources\RecepcionNuevosMateriales\RecepcionNuevosMaterialesResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRecepcionNuevosMateriales extends ListRecords
{
    protected static string $resource = RecepcionNuevosMaterialesResource::class;

    public function getTabs(): array
    {
        return [
            'por_recibir' => Tab::make('Por recibir en almacen')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'DOCUMENTO_RECEPCION_CARGADO_PROCURA')
                    ->whereNull('recepcion_procesada_at')),
            'en_transicion' => Tab::make('En zona de transicion')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'EN_TRANSICION_ALMACEN')),
            'pendiente_entrada' => Tab::make('Pendiente entrada final')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'CONFORMIDAD_POR_ITEMS_COMPLETA')),
        ];
    }
}
