<?php

namespace App\Filament\Resources\SolicitudesCompra\Pages;

use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Support\SolicitudCompraFlow;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListSolicitudCompras extends ListRecords
{
    protected static string $resource = SolicitudCompraResource::class;

    public function getTabs(): array
    {
        $user = auth()->user();

        return [
            'mis_solicitudes' => Tab::make('Mis solicitudes')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::requesterRequestsQuery($query, $user)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => SolicitudCompraResource::canCreate()),
        ];
    }
}
