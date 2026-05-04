<?php

namespace App\Filament\Resources\InspeccionOdcs\Pages;

use App\Filament\Resources\InspeccionOdcs\InspeccionOdcResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListInspeccionOdcs extends ListRecords
{
    protected static string $resource = InspeccionOdcResource::class;

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