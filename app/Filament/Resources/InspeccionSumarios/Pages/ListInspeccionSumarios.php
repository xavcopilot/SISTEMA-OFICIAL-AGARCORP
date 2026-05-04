<?php

namespace App\Filament\Resources\InspeccionSumarios\Pages;

use App\Filament\Resources\InspeccionSumarios\InspeccionSumariosResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListInspeccionSumarios extends ListRecords
{
    protected static string $resource = InspeccionSumariosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ayuda_flujo')
                ->label('Flujo')
                ->icon('heroicon-o-information-circle')
                ->color('gray')
                ->modalHeading('Inspeccion de Sumarios | Flujo')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalDescription('Aqui ves sumarios enviados por Procura. Puedes firmar la revision para enviarlo a Gerencia de Finanzas o rechazar con motivo para retorno a Procura.'),
        ];
    }
}
