<?php

namespace App\Filament\Resources\InspeccionOdcs\Tables;

use App\Support\OdcModalSummaryRenderer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class InspeccionOdcsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('correlativo_odc')
                    ->toggleable()
                    ->label('N° Control OC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sumario.correlativo_sdc')
                    ->toggleable()
                    ->label('N° SDC Asociado')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('solicitud_codigo_control')
                    ->toggleable()
                    ->label('N° Solicitud Asociada')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->codigo_control ?: '-'))
                    ->searchable(),

                TextColumn::make('proveedor.nombre')
                    ->toggleable()
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('departamento_solicitante')
                    ->toggleable()
                    ->label('Departamento')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('total_general')
                    ->toggleable()
                    ->label('Total general')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('estado')
                    ->toggleable()
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record): string => self::humanReadableWorkflowState($record))
                    ->color(fn ($record): string => self::colorForWorkflowState($record)),
            ])
            ->recordActions([
                Action::make('revisarOdc')
                    ->label('Revisar ODC')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Inspeccion ODC | ' . (string) ($record->correlativo_odc ?? ('#' . $record->id)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(OdcModalSummaryRenderer::render($record))),

                Action::make('vistaPdfOdcInspeccion')
                    ->label('Vista PDF ODC')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('ordenes-compra.formato.print', ['ordenCompra' => $record]))
                    ->openUrlInNewTab(),

                Action::make('aprobarValidacionOdc')
                    ->label('Enviar a Gerencia Finanzas')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record, $livewire): bool => (string) ($record->workflow_post_compra ?? '') === 'PENDIENTE_VALIDACION_FINANZAS'
                        && self::isInspectionInboxTab($livewire))
                    ->action(function ($record): void {
                        $record->forceFill([
                            'estado' => 'PENDIENTE_APROBACION',
                            'workflow_post_compra' => 'PENDIENTE_APROBACION_GERENCIA_FINANZAS',
                            'rechazo_etapa' => null,
                            'rechazo_comentario' => null,
                            'rechazo_por_user_id' => null,
                            'rechazo_en' => null,
                        ])->save();

                        Notification::make()
                            ->title('ODC validada')
                            ->body('La ODC fue revisada por Validacion Finanzas y enviada a Gerencia de Finanzas.')
                            ->success()
                            ->send();
                    }),

                Action::make('rechazarValidacionOdc')
                    ->label('Rechazar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn ($record, $livewire): bool => (string) ($record->workflow_post_compra ?? '') === 'PENDIENTE_VALIDACION_FINANZAS'
                        && self::isInspectionInboxTab($livewire))
                    ->form([
                        Textarea::make('rechazo_comentario')
                            ->label('Motivo de rechazo')
                            ->required()
                            ->rows(4)
                            ->maxLength(2000),
                    ])
                    ->action(function (array $data, $record): void {
                        $record->forceFill([
                            'estado' => 'RECHAZADA',
                            'workflow_post_compra' => 'BORRADOR_ODC',
                            'rechazo_etapa' => 'validacion_finanzas',
                            'rechazo_comentario' => trim((string) ($data['rechazo_comentario'] ?? '')),
                            'rechazo_por_user_id' => auth()->id(),
                            'rechazo_en' => now(),
                        ])->save();

                        Notification::make()
                            ->title('ODC rechazada en validacion')
                            ->body('La ODC regreso a Procura para correccion con motivo de rechazo.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function humanReadableWorkflowState(mixed $record): string
    {
        if ((string) ($record->rechazo_etapa ?? '') === 'validacion_finanzas') {
            return 'RECHAZADA EN VALIDACION FINANZAS';
        }

        return match ((string) ($record->workflow_post_compra ?? '')) {
            'PENDIENTE_VALIDACION_FINANZAS' => 'PENDIENTE VALIDACION FINANZAS',
            'PENDIENTE_APROBACION_GERENCIA_FINANZAS' => 'ENVIADA A GERENCIA FINANZAS',
            'PENDIENTE_PAGO_FINANZAS' => 'ENVIADA A PAGOS',
            'PAGO_REGISTRADO_FINANZAS' => 'PAGO REGISTRADO',
            'PAGADO_Y_EN_TRANSITO' => 'PAGADA Y EN TRANSITO',
            'DOCUMENTO_RECEPCION_CARGADO_PROCURA' => 'ENTREGADA A ALMACEN',
            'EN_TRANSICION_ALMACEN' => 'EN TRANSICION ALMACEN',
            'CONFORMIDAD_POR_ITEMS_COMPLETA' => 'PENDIENTE ENTRADA FINAL',
            'FACTURA_ENVIADA_ADMINISTRACION' => 'FACTURA ENVIADA A ADMINISTRACION',
            'BACKUP_FACTURA_COMPLETADO' => 'FACTURA CARGADA',
            'CERRADA_CONFORME' => 'CERRADA CONFORME',
            default => (string) ($record->estado ?? '-'),
        };
    }

    private static function colorForWorkflowState(mixed $record): string
    {
        if ((string) ($record->rechazo_etapa ?? '') === 'validacion_finanzas') {
            return 'danger';
        }

        return match ((string) ($record->workflow_post_compra ?? '')) {
            'PENDIENTE_VALIDACION_FINANZAS' => 'warning',
            'PENDIENTE_APROBACION_GERENCIA_FINANZAS' => 'info',
            'PENDIENTE_PAGO_FINANZAS',
            'PAGO_REGISTRADO_FINANZAS',
            'PAGADO_Y_EN_TRANSITO',
            'DOCUMENTO_RECEPCION_CARGADO_PROCURA',
            'EN_TRANSICION_ALMACEN',
            'CONFORMIDAD_POR_ITEMS_COMPLETA',
            'FACTURA_ENVIADA_ADMINISTRACION',
            'BACKUP_FACTURA_COMPLETADO',
            'CERRADA_CONFORME' => 'success',
            default => 'gray',
        };
    }

    private static function isInspectionInboxTab(mixed $livewire = null): bool
    {
        $activeTab = self::resolveActiveTab($livewire);

        return $activeTab === '' || $activeTab === 'mis_inspecciones';
    }

    private static function resolveActiveTab(mixed $livewire = null): string
    {
        if (! is_object($livewire)) {
            return '';
        }

        if (method_exists($livewire, 'getActiveTab')) {
            return (string) $livewire->getActiveTab();
        }

        if (property_exists($livewire, 'activeTab')) {
            return (string) ($livewire->activeTab ?? '');
        }

        return '';
    }
}

