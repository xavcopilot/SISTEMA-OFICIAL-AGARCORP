<?php

namespace App\Filament\Resources\ConsultarEntradas\Pages;

use App\Filament\Resources\ConsultarEntradas\ConsultarEntradasResource;
use App\Models\InventarioEntradaImport;
use App\Support\InventorySpreadsheetImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListConsultarEntradas extends ListRecords
{
    protected static string $resource = ConsultarEntradasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('processBatch')
                ->label('Importar y procesar CSV')
                ->icon('heroicon-o-wrench-screwdriver')
                ->modalHeading('Importar y procesar Entradas')
                ->modalDescription('Asigna un lote a las filas importadas manualmente y las procesa de una vez.')
                ->form([
                    TextInput::make('batch')
                        ->label('Lote')
                        ->placeholder('Opcional. Si lo dejas vacio, se genera uno solo.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $service = app(InventorySpreadsheetImportService::class);
                        $prepared = $service->normalizeAndBatchManualEntradas(filled($data['batch'] ?? null) ? (string) $data['batch'] : null);
                        $result = $service->processEntradasBatch((string) $prepared['batch'], auth()->id(), auth()->user()?->name);

                        $notification = Notification::make()
                            ->body('Lote ' . $result['batch'] . '. Filas normalizadas: ' . $prepared['normalized'] . '. Procesadas: ' . $result['processed'] . '. Fallidas: ' . $result['failed'] . '.');

                        if (($result['failed'] ?? 0) > 0 && ($result['processed'] ?? 0) > 0) {
                            $notification
                                ->title('Entradas procesadas parcialmente')
                                ->warning();
                        } elseif (($result['failed'] ?? 0) > 0) {
                            $notification
                                ->title('No se pudo procesar el lote de Entradas')
                                ->danger();
                        } else {
                            $notification
                                ->title('Entradas importadas y procesadas')
                                ->success();
                        }

                        $notification->send();
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
