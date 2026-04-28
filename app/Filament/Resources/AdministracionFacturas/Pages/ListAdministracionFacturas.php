<?php

namespace App\Filament\Resources\AdministracionFacturas\Pages;

use App\Filament\Resources\AdministracionFacturas\AdministracionFacturasResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAdministracionFacturas extends ListRecords
{
    protected static string $resource = AdministracionFacturasResource::class;

    public function getTabs(): array
    {
        return [
            'recibidas' => Tab::make('Facturas recibidas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNull('factura_procesada_administracion_at')),
            'cargadas' => Tab::make('Facturas cargadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('factura_procesada_administracion_at')),
        ];
    }
}
