<?php

namespace App\Filament\Resources\RecepcionNuevosMateriales\Tables;

use App\Models\OrdenCompra;
use App\Support\OrdenCompraRecepcionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecepcionNuevosMaterialesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('correlativo_odc')
                    ->label('Correlativo ODC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sumario.correlativo_sdc')
                    ->label('Sumario')
                    ->default('-'),

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('tipo_documento_recepcion')
                    ->label('Documento')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => (string) ($state ?: 'PENDIENTE')),

                TextColumn::make('workflow_post_compra')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state)),

                TextColumn::make('recepcion_procesada_at')
                    ->label('Recibido por almacen')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
            ])
            ->recordActions([
                Action::make('marcarZonaTransicion')
                    ->label('Pasar a Zona de Transicion')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->color('warning')
                    ->visible(fn (OrdenCompra $record): bool => self::canMarkTransition($record))
                    ->requiresConfirmation()
                    ->action(function (OrdenCompra $record): void {
                        try {
                            app(OrdenCompraRecepcionService::class)->marcarZonaTransicionAlmacen($record, auth()->user());

                            Notification::make()
                                ->title('Recepcion de almacen completada')
                                ->body('Se envio la ODC a zona de transicion y se notifico al solicitante.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo completar la recepcion')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function canMarkTransition(OrdenCompra $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $isWarehouse = str_contains(strtoupper((string) ($user->departamento?->nombre ?? '')), 'ALMAC');

        return $isWarehouse
            && filled($record->tipo_documento_recepcion)
            && blank($record->recepcion_procesada_at);
    }
}
