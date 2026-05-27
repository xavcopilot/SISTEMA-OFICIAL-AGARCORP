<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\InventorySpreadsheetImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('processManualImportedRows')
                ->label('Importar y procesar CSV')
                ->icon('heroicon-o-wrench-screwdriver')
                ->modalHeading('Importar y procesar Almacen ADV')
                ->modalDescription('Asigna un lote a las filas importadas manualmente y las procesa de una vez.')
                ->form([
                    TextInput::make('batch')
                        ->label('Lote')
                        ->placeholder('Opcional. Si lo dejas vacio, se genera uno solo.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $service = app(InventorySpreadsheetImportService::class);
                        $prepared = $service->normalizeAndBatchManualProducts(filled($data['batch'] ?? null) ? (string) $data['batch'] : null);
                        $result = $service->processProductsBatch((string) $prepared['batch']);

                        Notification::make()
                            ->title('Almacen ADV importado y procesado')
                            ->body('Lote ' . $result['batch'] . '. Filas normalizadas: ' . $prepared['normalized'] . '. Procesadas: ' . $result['processed'] . '. Fallidas: ' . $result['failed'] . '.')
                            ->success()
                            ->send();
                    } catch (Throwable $throwable) {
                        Notification::make()
                            ->title('No se pudo importar y procesar')
                            ->body($throwable->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('exportStockExcel')
                ->label('Exportar Stock (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('inventario.export', ['format' => 'xlsx']))
                ->openUrlInNewTab(),
            Action::make('exportStockCsv')
                ->label('Exportar Stock (CSV)')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('inventario.export', ['format' => 'csv']))
                ->visible(false)
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
                ->visible(false)
                ->openUrlInNewTab(),
        ];
    }
}
