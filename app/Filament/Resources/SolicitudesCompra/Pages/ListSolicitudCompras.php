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
            'historial_conformidades' => Tab::make('Historial de Conformidades')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::requesterConformidadHistoryQuery($query, $user)),
            'historial_solicitudes' => Tab::make('Historial de Solicitudes')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::requesterCompletedHistoryQuery($query, $user)),
            'borradores' => Tab::make('Borradores')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::requesterDraftsQuery($query, $user)),
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
