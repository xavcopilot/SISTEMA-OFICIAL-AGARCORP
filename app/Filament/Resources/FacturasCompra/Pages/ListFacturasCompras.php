<?php

namespace App\Filament\Resources\FacturasCompra\Pages;

use App\Filament\Resources\FacturasCompra\FacturasCompraResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFacturasCompras extends ListRecords
{
    protected static string $resource = FacturasCompraResource::class;

    public function getTabs(): array
    {
        return [
            'por_enviar' => Tab::make('Facturas por enviar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNull('factura_enviada_administracion_at')),
            'enviadas' => Tab::make('Facturas enviadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('factura_enviada_administracion_at')),
            'todas' => Tab::make('Todas'),
        ];
    }
}
