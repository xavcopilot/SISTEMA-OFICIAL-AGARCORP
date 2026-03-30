<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportStockExcel')
                ->label('Exportar Stock (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('inventario.export', ['format' => 'xlsx']))
                ->openUrlInNewTab(),
            Action::make('exportStockCsv')
                ->label('Exportar Stock (CSV)')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('inventario.export', ['format' => 'csv']))
                ->openUrlInNewTab(),
            Action::make('exportInventarioExcel')
                ->label('Exportar Hoja Inventario (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('inventario.maestro.export', ['format' => 'xlsx']))
                ->openUrlInNewTab(),
            Action::make('exportInventarioCsv')
                ->label('Exportar Hoja Inventario (CSV)')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('inventario.maestro.export', ['format' => 'csv']))
                ->openUrlInNewTab(),
        ];
    }
}
