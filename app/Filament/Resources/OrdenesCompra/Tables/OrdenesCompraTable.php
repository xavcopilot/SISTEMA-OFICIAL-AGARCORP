<?php

namespace App\Filament\Resources\OrdenesCompra\Tables;

use App\Support\ActivityNotification;
use App\Support\OrdenCompraConformidadService;
use App\Support\OrdenCompraRecepcionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdenesCompraTable
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

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state)),

                TextColumn::make('tipo_documento_recepcion')
                    ->label('Recepcion')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => (string) ($state ? str_replace('_', ' ', $state) : 'PENDIENTE'))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'FACTURA' => 'success',
                        'NOTA' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('factura_pendiente')
                    ->label('Alerta')
                    ->badge()
                    ->state(fn ($record): string => (bool) $record->factura_pendiente ? 'FACTURA PENDIENTE' : 'OK')
                    ->color(fn ($record): string => (bool) $record->factura_pendiente ? 'danger' : 'success'),

                TextColumn::make('sub_total')
                    ->label('Sub total')
                    ->money('VES')
                    ->sortable(),

                TextColumn::make('iva_16')
                    ->label('IVA 16%')
                    ->money('VES')
                    ->sortable(),

                TextColumn::make('gastos_adicionales')
                    ->label('Gastos adicionales')
                    ->money('VES')
                    ->sortable(),

                TextColumn::make('total_general')
                    ->label('Total general')
                    ->money('VES')
                    ->sortable(),

                TextColumn::make('conformidad_solicitante_at')
                    ->label('Conformidad')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
            ])
            ->filters([
                Filter::make('bandejaAdministracion')
                    ->label('Bandeja Administracion (Facturas)')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('tipo_documento_recepcion', 'FACTURA')
                        ->whereNotNull('factura_path')
                        ->whereNull('factura_procesada_administracion_at')),
            ])
            ->recordActions([
                Action::make('previewPdf')
                    ->label('Vista previa ODC')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('ordenes-compra.formato.print', ['ordenCompra' => $record]))
                    ->openUrlInNewTab(),

                Action::make('procesarRecepcion')
                    ->label('Procesar Recepcion')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('warning')
                    ->visible(fn ($record): bool => self::canProcessReception($record))
                    ->form([
                        Radio::make('tipo_documento_recepcion')
                            ->label('Llego con Factura o Nota de Entrega?')
                            ->options([
                                'FACTURA' => 'Factura',
                                'NOTA' => 'Nota de Entrega',
                            ])
                            ->required()
                            ->live(),

                        FileUpload::make('factura_path')
                            ->label('Imagen de Factura')
                            ->image()
                            ->disk('public')
                            ->directory('ordenes-compra/facturas')
                            ->visibility('public')
                            ->required(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'FACTURA')
                            ->visible(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'FACTURA'),
                    ])
                    ->action(function (array $data, $record): void {
                        try {
                            app(OrdenCompraRecepcionService::class)->procesarRecepcion(
                                $record,
                                auth()->user(),
                                (string) ($data['tipo_documento_recepcion'] ?? ''),
                                $data['factura_path'] ?? null,
                            );

                            Notification::make()
                                ->title('Recepcion procesada')
                                ->body((string) ($data['tipo_documento_recepcion'] ?? '') === 'NOTA'
                                    ? 'Items enviados a ZONA DE TRANSICION. Alerta activa: Factura Pendiente.'
                                    : 'Items enviados a ZONA DE TRANSICION y factura enviada a bandeja de Administracion.')
                                ->success()
                                ->send();

                            ActivityNotification::record(
                                auth()->user(),
                                'Recepcion de ODC procesada',
                                'Se proceso la recepcion de la ODC ' . (string) $record->correlativo_odc . '.',
                                'success'
                            );
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo procesar la recepcion')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('aceptarConformidad')
                    ->label('Aceptar Conformidad')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar conformidad del solicitante')
                    ->modalDescription('Al confirmar, se ejecuta la entrada oficial en inventario y el item se marca como entregado al solicitante.')
                    ->visible(fn ($record): bool => self::canAcceptConformity($record))
                    ->action(function ($record): void {
                        try {
                            app(OrdenCompraConformidadService::class)->aceptar($record, auth()->user());

                            Notification::make()
                                ->title('Conformidad registrada')
                                ->body('Se ejecuto la entrada oficial en inventario y se completo el ciclo.')
                                ->success()
                                ->send();

                            ActivityNotification::record(
                                auth()->user(),
                                'Conformidad de ODC registrada',
                                'Se registro conformidad para la ODC ' . (string) $record->correlativo_odc . '.',
                                'success'
                            );
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo registrar la conformidad')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('marcarFacturaProcesada')
                    ->label('Marcar Factura Procesada')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('info')
                    ->visible(fn ($record): bool => self::canMarkInvoiceProcessed($record))
                    ->action(function ($record): void {
                        $record->forceFill([
                            'factura_procesada_administracion_at' => now(),
                        ])->save();

                        Notification::make()
                            ->title('Factura enviada a proceso contable')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Factura marcada como procesada',
                            'La factura de la ODC ' . (string) $record->correlativo_odc . ' fue marcada para cierre contable.',
                            'success'
                        );
                    }),

                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function canProcessReception(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        $canOperate = $user->can('ProcessReception:OrdenCompra');

        return $canOperate && ! filled($record->recepcion_procesada_at);
    }

    private static function canAcceptConformity(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        $solicitanteId = (int) ($record->sumario?->solicitudCompra?->solicitado_por_user_id ?? 0);

        return $solicitanteId > 0
            && (int) $user->id === $solicitanteId
            && filled($record->recepcion_procesada_at)
            && blank($record->conformidad_solicitante_at);
    }

    private static function canMarkInvoiceProcessed(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        $isAdministracion = (string) ($user->departamento?->nombre ?? '') === 'ADMINISTRACIÓN'
            || (string) ($user->departamento?->nombre ?? '') === 'ADMINISTRACION';

        return $isAdministracion
            && (string) ($record->tipo_documento_recepcion ?? '') === 'FACTURA'
            && filled($record->factura_path)
            && blank($record->factura_procesada_administracion_at);
    }
}
