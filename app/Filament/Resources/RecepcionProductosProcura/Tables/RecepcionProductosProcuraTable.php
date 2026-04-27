<?php

namespace App\Filament\Resources\RecepcionProductosProcura\Tables;

use App\Support\OrdenCompraRecepcionService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecepcionProductosProcuraTable
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
                    ->state('PAGADO Y EN TRANSITO')
                    ->color('info'),

                TextColumn::make('total_general')
                    ->label('Total general')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('comprobante_pago_path')
                    ->label('Comprobante de pago')
                    ->state(fn ($record): string => filled($record->comprobante_pago_path) ? 'Ver imagen' : 'Sin imagen')
                    ->url(fn ($record): ?string => filled($record->comprobante_pago_path)
                        ? route('ordenes-compra.comprobante.download', ['ordenCompra' => $record])
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('marcarEntregadoAlmacen')
                    ->label('Cargar Nota/Factura y enviar a Almacén')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->color('warning')
                    ->modalHeading('Cargar documento para Almacen')
                    ->modalDescription('Agregar Nota de Entrega o Factura segun sea el caso para enviar a Almacen.')
                    ->form([
                        Radio::make('tipo_documento_recepcion')
                            ->label('Documento recibido')
                            ->options([
                                'FACTURA' => 'Factura',
                                'NOTA' => 'Nota de Entrega',
                            ])
                            ->required()
                            ->live()
                            ->default('NOTA'),

                        FileUpload::make('factura_path')
                            ->label('Adjuntar Factura')
                            ->disk('odc_facturas')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->required(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'FACTURA')
                            ->visible(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'FACTURA'),

                        FileUpload::make('nota_entrega_path')
                            ->label('Adjuntar Nota de Entrega')
                            ->disk('odc_notas_entrega')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->required(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'NOTA')
                            ->visible(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'NOTA'),
                    ])
                    ->action(function (array $data, $record): void {
                        try {
                            $tipoDocumento = (string) ($data['tipo_documento_recepcion'] ?? '');
                            $documentoPath = $tipoDocumento === 'NOTA'
                                ? ($data['nota_entrega_path'] ?? null)
                                : ($data['factura_path'] ?? null);

                            app(OrdenCompraRecepcionService::class)->cargarDocumentoProcura(
                                $record,
                                auth()->user(),
                                $tipoDocumento,
                                $documentoPath,
                            );

                            Notification::make()
                                ->title('Producto entregado a almacen')
                                ->body('La ODC paso a Recepcion de Materiales Nuevos con su documento de soporte para validacion de Almacen.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo marcar la entrega')
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
