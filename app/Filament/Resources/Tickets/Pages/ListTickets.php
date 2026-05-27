<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Tickets\Widgets\TicketStatsOverview; // Importar el widget
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    // ESTO ES LO QUE DEBES AGREGAR:
    protected function getHeaderWidgets(): array
    {
        return [
            TicketStatsOverview::class,
        ];
    }
}
