<?php

namespace App\Filament\Resources\ConsultarEntradas\Pages;

use App\Filament\Resources\ConsultarEntradas\ConsultarEntradasResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListConsultarEntradas extends ListRecords
{
    protected static string $resource = ConsultarEntradasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('inventario.entradas.export', ['format' => 'xlsx']))
                ->openUrlInNewTab(),
            Action::make('exportCsv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('inventario.entradas.export', ['format' => 'csv']))
                ->openUrlInNewTab(),
        ];
    }
}
