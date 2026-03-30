<?php

namespace App\Filament\Resources\ConsultarSalidas\Pages;

use App\Filament\Resources\ConsultarSalidas\ConsultarSalidasResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListConsultarSalidas extends ListRecords
{
    protected static string $resource = ConsultarSalidasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('inventario.salidas.export', ['format' => 'xlsx']))
                ->openUrlInNewTab(),
            Action::make('exportCsv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('inventario.salidas.export', ['format' => 'csv']))
                ->openUrlInNewTab(),
        ];
    }
}