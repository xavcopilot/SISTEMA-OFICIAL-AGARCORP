<?php

namespace App\Filament\Resources\RecepcionMaterialesNuevos\Tables;

use App\Support\OrdenCompraRecepcionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecepcionMaterialesNuevosTable
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
                    ->default('-')
                    ->searchable(),

                TextColumn::make('solicitud_codigo_control')
                    ->label('Solicitud')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->codigo_control ?: '-'))
                    ->searchable(),

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('para_ser_usado_en')
                    ->label('Para ser usado en')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->para_ser_usado_en ?: '-'))
                    ->wrap(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state('RECIBIDO EN ALMACEN')
                    ->color('warning'),

                TextColumn::make('tipo_documento_recepcion')
                    ->label('Documento recibido')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ((string) $state) {
                        'FACTURA' => 'FACTURA',
                        'NOTA' => 'NOTA DE ENTREGA',
                        default => 'SIN DOCUMENTO',
                    })
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'FACTURA' => 'success',
                        'NOTA' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('total_general')
                    ->label('Total general')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('factura_path')
                    ->label('Soporte de entrega')
                    ->state(fn ($record): string => filled($record->factura_path) ? 'Descargar documento' : 'Sin documento')
                    ->url(fn ($record): ?string => filled($record->factura_path)
                        ? route('ordenes-compra.documento-recepcion.download', ['ordenCompra' => $record])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('comprobante_pago_path')
                    ->label('Comprobante de pago')
                    ->state(fn ($record): string => filled($record->comprobante_pago_path) ? 'Ver imagen' : 'Sin imagen')
                    ->url(fn ($record): ?string => filled($record->comprobante_pago_path)
                        ? route('ordenes-compra.comprobante.download', ['ordenCompra' => $record])
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('marcarZonaTransicion')
                    ->label('Marcar en Zona de transicion')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar recepcion en almacen')
                    ->modalDescription('Esta accion notificara al solicitante y habilitara la conformidad de materiales.')
                    ->action(function ($record): void {
                        try {
                            app(OrdenCompraRecepcionService::class)->marcarZonaTransicionAlmacen($record, auth()->user());

                            Notification::make()
                                ->title('Material enviado a zona de transicion')
                                ->body('Se notifico al solicitante para conformidad por item.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo mover a zona de transicion')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('vistaPreviaOdc')
                    ->label('Vista previa ODC')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('ordenes-compra.formato.print', ['ordenCompra' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
