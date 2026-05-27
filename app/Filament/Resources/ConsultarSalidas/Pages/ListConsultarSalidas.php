<?php

namespace App\Filament\Resources\ConsultarSalidas\Pages;

use App\Filament\Resources\ConsultarSalidas\ConsultarSalidasResource;
use App\Models\InventarioSalidaImport;
use App\Support\InventorySpreadsheetImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListConsultarSalidas extends ListRecords
{
    protected static string $resource = ConsultarSalidasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('processBatch')
                ->label('Importar y procesar CSV')
                ->icon('heroicon-o-wrench-screwdriver')
                ->modalHeading('Importar y procesar Salidas')
                ->modalDescription('Asigna un lote a las filas importadas manualmente y las procesa de una vez.')
                ->form([
                    TextInput::make('batch')
                        ->label('Lote')
                        ->placeholder('Opcional. Si lo dejas vacio, se genera uno solo.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $service = app(InventorySpreadsheetImportService::class);
                        $prepared = $service->normalizeAndBatchManualSalidas(filled($data['batch'] ?? null) ? (string) $data['batch'] : null);
                        $result = $service->processSalidasBatch((string) $prepared['batch'], auth()->id(), auth()->user()?->name);

                        Notification::make()
                            ->title('Salidas importadas y procesadas')
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