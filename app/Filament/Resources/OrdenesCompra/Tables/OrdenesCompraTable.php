<?php

namespace App\Filament\Resources\OrdenesCompra\Tables;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Models\Departamento;
use App\Models\Product;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\User;
use App\Support\ActivityNotification;
use App\Support\BcvRateService;
use App\Support\OrdenCompraAdministracionService;
use App\Support\OrdenCompraConformidadService;
use App\Support\OdcModalSummaryRenderer;
use App\Support\OrdenCompraRecepcionService;
use App\Support\SumarioModalSummaryRenderer;
use App\Support\SumarioFinanceApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OrdenesCompraTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('correlativo_sdc')
                    ->toggleable()
                    ->label('N° Control SDC')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('solicitud_codigo_control_creacion_odc')
                    ->toggleable()
                    ->label('N° Control Solicitud')
                    ->state(fn ($record): string => (string) ($record->solicitud_codigo_control ?: $record->solicitud_compra_id ?: '-'))
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('solicitante_nombre')
                    ->toggleable()
                    ->label('Solicitante')
                    ->state(fn ($record): string => (string) ($record->solicitante_nombre ?: '-'))
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('fecha')
                    ->toggleable()
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('procedencia')
                    ->toggleable()
                    ->label('Procedencia')
                    ->badge()
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('tipo_orden')
                    ->toggleable()
                    ->label('Tipo orden')
                    ->badge()
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('estado_creacion_odc')
                    ->toggleable()
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record): string => self::humanReadableSumarioState((string) ($record->workflow_estado ?: $record->estado)))
                    ->color('warning')
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('odc_faltantes_creacion_odc')
                    ->toggleable()
                    ->label('ODC faltantes')
                    ->state(fn ($record): string => 'Faltan ' . self::pendingOdcGroupsCountForTable($record))
                    ->badge()
                    ->color('warning')
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('total_prov_1_creacion_odc')
                    ->toggleable()
                    ->label('Total Prov. 1')
                    ->state(fn ($record): float => self::selectedProviderTotalForTable($record, 1))
                    ->money('USD')
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('total_prov_2_creacion_odc')
                    ->toggleable()
                    ->label('Total Prov. 2')
                    ->state(fn ($record): float => self::selectedProviderTotalForTable($record, 2))
                    ->money('USD')
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('total_prov_3_creacion_odc')
                    ->toggleable()
                    ->label('Total Prov. 3')
                    ->state(fn ($record): float => self::selectedProviderTotalForTable($record, 3))
                    ->money('USD')
                    ->visible(fn ($livewire): bool => self::isCreationOdcTab($livewire)),

                TextColumn::make('correlativo_odc')
                    ->toggleable()
                    ->label('N° Control OC')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire) || self::isPagosOdcTab($livewire)),

                TextColumn::make('sumario.correlativo_sdc')
                    ->toggleable()
                    ->label('N° Control SDC')
                    ->default('-')
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire) || self::isPagosOdcTab($livewire)),

                TextColumn::make('solicitud_codigo_control')
                    ->toggleable()
                    ->label('N° Control Solicitud')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->codigo_control ?: '-'))
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire) || self::isPagosOdcTab($livewire)),

                TextColumn::make('proveedor.nombre')
                    ->toggleable()
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire) || self::isPagosOdcTab($livewire)),

                TextColumn::make('departamento_solicitante')
                    ->toggleable()
                    ->label('Departamento')
                    ->default('-')
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire) || self::isPagosOdcTab($livewire)),

                TextColumn::make('para_ser_usado_en_pagos_odc')
                    ->toggleable()
                    ->label('Para ser usado en')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->para_ser_usado_en ?: '-'))
                    ->wrap()
                    ->visible(fn ($livewire): bool => self::isPagosOdcTab($livewire)),

                TextColumn::make('estado')
                    ->toggleable()
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record, $livewire): string => self::isHistorialOdcTab($livewire)
                        ? self::resolveHistorialEstado((string) ($record->workflow_post_compra ?? ''), (string) ($record->estado ?? ''))
                        : str_replace('_', ' ', (string) ($record->estado ?? '')))
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire)),

                TextColumn::make('sub_estado_historial')
                    ->toggleable()
                    ->label('Sub Estado')
                    ->state(fn ($record): string => self::humanReadableSubEstado((string) ($record->workflow_post_compra ?? '')))
                    ->badge()
                    ->color(fn ($record): string => self::subEstadoColor((string) ($record->workflow_post_compra ?? '')))
                    ->visible(fn ($livewire): bool => self::isHistorialOdcTab($livewire)),

                TextColumn::make('workflow_post_compra')
                    ->toggleable()
                    ->label('Flujo post-compra')
                    ->badge()
                    ->state(fn ($record): string => (string) ($record->workflow_post_compra ?: 'PENDIENTE_PAGO_FINANZAS'))
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'PENDIENTE_APROBACION_GERENCIA_FINANZAS' => 'warning',
                        'PENDIENTE_PAGO_FINANZAS' => 'warning',
                        'PAGO_REGISTRADO_FINANZAS' => 'info',
                        'PAGO_CONFIRMADO_PROCURA', 'ESPERANDO_PRODUCTO', 'PAGADO_Y_EN_TRANSITO' => 'info',
                        'DOCUMENTO_RECEPCION_CARGADO_PROCURA' => 'warning',
                        'EN_TRANSICION_ALMACEN' => 'warning',
                        'CONFORMIDAD_POR_ITEMS_COMPLETA' => 'info',
                        'FACTURA_ENVIADA_ADMINISTRACION', 'BACKUP_FACTURA_COMPLETADO' => 'success',
                        'CERRADA_CONFORME' => 'success',
                        'RECHAZADA_SOLICITANTE' => 'danger',
                        default => 'gray',
                    })
                    ->visible(fn ($livewire): bool => false),

                TextColumn::make('tipo_documento_recepcion')
                    ->toggleable()
                    ->label('Recepcion')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => (string) ($state ? str_replace('_', ' ', $state) : 'PENDIENTE'))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'FACTURA' => 'success',
                        'NOTA' => 'warning',
                        default => 'gray',
                    })
                    ->visible(fn ($livewire): bool => false),

                TextColumn::make('factura_pendiente')
                    ->toggleable()
                    ->label('Alerta')
                    ->badge()
                    ->state(fn ($record): string => (bool) $record->factura_pendiente ? 'FACTURA PENDIENTE' : 'OK')
                    ->color(fn ($record): string => (bool) $record->factura_pendiente ? 'danger' : 'success')
                    ->visible(fn ($livewire): bool => false),

                TextColumn::make('sub_total')
                    ->toggleable()
                    ->label('Sub total')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable()
                    ->visible(fn ($livewire): bool => false),

                TextColumn::make('iva_16')
                    ->toggleable()
                    ->label('IVA 16%')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable()
                    ->visible(fn ($livewire): bool => false),

                TextColumn::make('gastos_adicionales')
                    ->toggleable()
                    ->label('Gastos adicionales')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable()
                    ->visible(fn ($livewire): bool => false),

                TextColumn::make('total_general')
                    ->toggleable()
                    ->label('Total general')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable()
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire)),

                TextColumn::make('conformidad_solicitante_at')
                    ->toggleable()
                    ->label('Conformidad')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente')
                    ->visible(fn ($livewire): bool => self::isCorreccionesOrHistorialOdcTab($livewire)),

                TextColumn::make('comprobante_pago_path')
                    ->toggleable()
                    ->label('Comprobante de pago')
                    ->state(fn ($record): string => filled($record->comprobante_pago_path) ? 'Ver imagen' : 'Sin imagen')
                    ->url(fn ($record): ?string => filled($record->comprobante_pago_path)
                        ? route('ordenes-compra.comprobante.download', ['ordenCompra' => $record])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($livewire): bool => self::isPagosOdcTab($livewire)),
            ])
            ->filters([
                Filter::make('bandejaAdministracion')
                    ->label('Bandeja Administracion (Facturas)')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('tipo_documento_recepcion', 'FACTURA')
                        ->whereNotNull('factura_path')
                        ->whereNull('factura_procesada_administracion_at'))
                    ->visible(fn ($livewire): bool => ! self::isCreationOdcTab($livewire)),
            ])
            ->recordActions([
                Action::make('verSumarioPendienteOdc')
                    ->label('Ver sumario')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Resumen | Sumario ' . (string) ($record->correlativo_sdc ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderSumarioSummaryModalForRecord($record)))
                    ->visible(fn ($record): bool => self::isPendingSumarioRecord($record)),

                Action::make('creacionOdcDesdeFila')
                    ->label('Creacion de ODC')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('success')
                    ->modalHeading(fn ($record): string => 'Creacion de ODC | Sumario ' . (string) ($record->correlativo_sdc ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderPendingOdcModalForRecord($record)))
                    ->visible(fn ($record): bool => self::isPendingSumarioRecord($record) && (bool) auth()->user()?->can('GenerateOdcs:Sumario')),

                Action::make('previewPdf')
                    ->label('Vista PDF ODC')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('ordenes-compra.formato.print', ['ordenCompra' => $record]))
                    ->visible(fn ($record, $livewire): bool => ! self::isPendingSumarioRecord($record)
                        && self::isHistorialOdcTab($livewire))
                    ->openUrlInNewTab(),

                Action::make('verResumenOdc')
                    ->label('Ver resumen ODC')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Resumen ODC | ' . (string) ($record->correlativo_odc ?? ('#' . $record->id)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(OdcModalSummaryRenderer::render($record)))
                    ->visible(fn ($record, $livewire): bool => ! self::isPendingSumarioRecord($record)
                        && (self::isOdcCorreccionesTab($livewire) || self::isHistorialOdcTab($livewire))),

                Action::make('editarOdcCorreccion')
                    ->label('Editar ODC')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->url(fn ($record): string => OrdenCompraResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn ($record, $livewire): bool => ! self::isPendingSumarioRecord($record)
                        && self::isOdcCorreccionesTab($livewire)
                        && (string) ($record->estado ?? '') === 'RECHAZADA'
                        && in_array((string) ($record->rechazo_etapa ?? ''), ['gerencia_finanzas', 'validacion_finanzas'], true)),

                Action::make('verSumarioOdc')
                    ->label('Ver sumario')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Resumen | Sumario ' . (string) ($record->sumario?->correlativo_sdc ?? '-'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderSumarioSummaryModalForOdc($record)))
                    ->visible(fn ($record, $livewire): bool => ! self::isPendingSumarioRecord($record)
                        && self::isHistorialOdcTab($livewire)),

                Action::make('verSolicitudOdc')
                    ->label('Ver solicitud')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->modalHeading(fn ($record): string => 'Solicitud asociada | ' . (string) ($record->sumario?->solicitudCompra?->codigo_control ?? '-'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn ($record): array => self::getSolicitudViewFormDataForOdc($record))
                    ->schema(self::getSolicitudViewSchemaForOdc())
                    ->visible(fn ($record, $livewire): bool => ! self::isPendingSumarioRecord($record)
                        && self::isHistorialOdcTab($livewire)),

                Action::make('verComprobanteHistorial')
                    ->label('Ver comprobante')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('info')
                    ->url(fn ($record): ?string => filled($record->comprobante_pago_path)
                        ? route('ordenes-compra.comprobante.download', ['ordenCompra' => $record])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => false),

                Action::make('verDocumentoRecepcionHistorial')
                    ->label(fn ($record): string => ((string) ($record->tipo_documento_recepcion ?? '') === 'NOTA' && ! $record->hasFacturaRecepcion())
                        ? 'Ver nota de entrega'
                        : 'Ver factura')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('warning')
                    ->url(fn ($record): ?string => $record->hasFacturaRecepcion() || $record->hasNotaEntregaRecepcion()
                        ? route('ordenes-compra.documento-recepcion.download', [
                            'ordenCompra' => $record,
                            'documento' => ((string) ($record->tipo_documento_recepcion ?? '') === 'NOTA' && ! $record->hasFacturaRecepcion()) ? 'nota' : 'factura',
                        ])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => false),

                Action::make('aprobarGerenciaFinanzas')
                    ->label('Gerencia Finanzas: Aprobar para pago')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('warning')
                    ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                        && self::canApproveByGerenciaFinanzas($record))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $currentRate = app(BcvRateService::class)->rateForOrderCreation();

                        $record->forceFill([
                            'tasa_bcv' => $currentRate !== null
                                ? round((float) $currentRate, 4)
                                : $record->tasa_bcv,
                            'estado' => 'APROBADA',
                            'workflow_post_compra' => 'PENDIENTE_PAGO_FINANZAS',
                            'aprobado_por_user_id' => auth()->id(),
                            'aprobado_firmado_at' => now(),
                        ])->save();

                        self::notifyFinanzasPaymentEnabled($record);

                        Notification::make()
                            ->title('ODC aprobada por Gerencia de Finanzas')
                            ->body('La ODC fue aprobada y ya esta disponible en la bandeja de pago de Finanzas.')
                            ->success()
                            ->send();
                    }),

                Action::make('registrarPagoFinanzas')
                    ->label('Finanzas: Registrar Pago')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                        && self::canRegisterFinancePayment($record))
                    ->form([
                        TextInput::make('tasa_bcv')
                            ->label('Tasa BCV a congelar')
                            ->numeric()
                            ->step('0.0000')
                            ->default(fn ($record): float => round((float) ($record->tasa_bcv ?? 0), 4))
                            ->required(),
                        TextInput::make('monto_pagado')
                            ->label('Monto pagado')
                            ->numeric()
                            ->required(),
                        TextInput::make('referencia_pago')
                            ->label('Referencia / Nro operacion')
                            ->maxLength(255)
                            ->required(),
                        FileUpload::make('comprobantes_pago_paths')
                            ->label('Comprobantes bancarios')
                            ->multiple()
                            ->disk('odc_comprobantes')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(12000)
                            ->helperText('Tamano maximo recomendado: 12 MB por archivo.')
                            ->required(),
                        Textarea::make('observacion_pago')
                            ->label('Observacion')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record): void {
                        $comprobantes = collect($data['comprobantes_pago_paths'] ?? [])
                            ->filter(fn ($path): bool => filled($path))
                            ->values()
                            ->all();

                        if ($comprobantes === []) {
                            Notification::make()
                                ->title('Comprobantes requeridos')
                                ->body('Debes subir al menos un comprobante bancario.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->forceFill([
                            'tasa_bcv' => round((float) ($data['tasa_bcv'] ?? 0), 4),
                            'monto_pagado' => round((float) ($data['monto_pagado'] ?? 0), 2),
                            'referencia_pago' => (string) ($data['referencia_pago'] ?? ''),
                            'comprobante_pago_path' => $comprobantes[0] ?? null,
                            'observacion_pago' => (string) ($data['observacion_pago'] ?? ''),
                            'pago_registrado_at' => now(),
                            'pago_por_user_id' => auth()->id(),
                            'estado' => 'PAGADA',
                            'workflow_post_compra' => 'PAGO_REGISTRADO_FINANZAS',
                        ])->save();

                        foreach ($comprobantes as $path) {
                            $record->comprobantes()->create([
                                'archivo_path' => (string) $path,
                                'subido_por_user_id' => auth()->id(),
                            ]);
                        }

                        self::notifyProcuraPaymentRegistered($record);

                        Notification::make()
                            ->title('Pago registrado')
                            ->body('Finanzas registro el pago y notifico a Procura con el comprobante.')
                            ->success()
                            ->send();
                    }),

                Action::make('confirmarPagoProcura')
                    ->label('Procura: Marcar Pagado y En Transito')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('info')
                    ->visible(fn ($record, $livewire): bool => self::isPagosOdcTab($livewire)
                        && ! self::isOdcCorreccionesTab($livewire)
                        && self::canConfirmPaymentByProcura($record))
                    ->action(function ($record): void {
                        $record->forceFill([
                            'confirmado_procura_at' => now(),
                            'confirmado_por_user_id' => auth()->id(),
                            'estado' => 'EN_ESPERA_DE_PRODUCTO',
                            'workflow_post_compra' => 'PAGADO_Y_EN_TRANSITO',
                        ])->save();

                        SumarioItem::query()
                            ->whereIn('id', $record->items()->pluck('sumario_item_id')->filter()->values()->all())
                            ->update(['sub_estado' => 'PAGADO_Y_EN_TRANSITO']);

                        Notification::make()
                            ->title('Pago y transito confirmado por Procura')
                            ->body('La orden ahora figura como Pagado y En Transito.')
                            ->success()
                            ->send();
                    }),

                Action::make('cargarDocumentoRecepcionProcura')
                    ->label('Procura: Cargar Factura/Nota')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('warning')
                    ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                        && self::canUploadReceptionDocumentByProcura($record))
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
                            ->label('Adjuntar Factura')
                            ->disk('odc_facturas')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(12000)
                            ->helperText('Tamano maximo recomendado: 12 MB por archivo.')
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
                            ->maxSize(12000)
                            ->helperText('Tamano maximo recomendado: 12 MB por archivo.')
                            ->required(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'NOTA')
                            ->visible(fn (callable $get): bool => (string) ($get('tipo_documento_recepcion') ?? '') === 'NOTA'),
                    ])
                    ->action(function (array $data, $record): void {
                        try {
                            $tipoDocumento = (string) ($data['tipo_documento_recepcion'] ?? '');
                            $documentoPath = strtoupper($tipoDocumento) === 'NOTA'
                                ? ($data['nota_entrega_path'] ?? null)
                                : ($data['factura_path'] ?? null);

                            app(OrdenCompraRecepcionService::class)->cargarDocumentoProcura(
                                $record,
                                auth()->user(),
                                $tipoDocumento,
                                $documentoPath,
                            );

                            Notification::make()
                                ->title('Documento de recepcion cargado')
                                ->body($tipoDocumento === 'NOTA'
                                    ? 'Se cargo NOTA. Almacen ya puede recibir en el modulo Recepcion de Nuevos Materiales.'
                                    : 'Se cargo FACTURA y se notifico a Finanzas para su bandeja de factura.')
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

                Action::make('marcarZonaTransicionAlmacen')
                    ->label('Almacen: Pasar a Zona de Transicion')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->color('info')
                    ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                        && self::canMarkTransitionByWarehouse($record))
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar recepcion en almacen')
                    ->modalDescription('Esta accion habilita la conformidad por item para el solicitante.')
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

                Action::make('enviarFacturaAdministracion')
                    ->label('Finanzas: Enviar factura a Administracion')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                        && self::canSendInvoiceToAdministration($record))
                    ->action(function ($record): void {
                        $record->forceFill([
                            'factura_enviada_administracion_at' => now(),
                            'factura_enviada_por_user_id' => auth()->id(),
                            'workflow_post_compra' => 'FACTURA_ENVIADA_ADMINISTRACION',
                        ])->save();

                        self::notifyAdministracionInvoiceReady($record);

                        Notification::make()
                            ->title('Factura enviada a Administracion')
                            ->body('Administracion fue notificada para carga manual y respaldo contable.')
                            ->success()
                            ->send();
                    }),

                self::makeAdministracionFacturaAction(),

                Action::make('conformidadMaterialesPorItem')
                    ->label('Conformidad de Materiales')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                        && self::canRegisterItemConformity($record))
                    ->form([
                        Repeater::make('items_conformidad')
                            ->label('Decision por item')
                            ->default(fn ($record): array => self::buildConformidadRows($record))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('orden_compra_item_id')->required(),
                                TextInput::make('item')
                                    ->label('Item')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('descripcion')
                                    ->label('Descripcion')
                                    ->disabled()
                                    ->dehydrated(false),
                                Radio::make('decision')
                                    ->label('Decision solicitante')
                                    ->options([
                                        'ACEPTADO' => 'Aceptar',
                                        'RECHAZADO' => 'Rechazar',
                                    ])
                                    ->required()
                                    ->live(),
                                Textarea::make('motivo')
                                    ->label('Motivo (si rechaza)')
                                    ->rows(2)
                                    ->required(fn (callable $get): bool => (string) ($get('decision') ?? '') === 'RECHAZADO')
                                    ->visible(fn (callable $get): bool => (string) ($get('decision') ?? '') === 'RECHAZADO'),
                            ])
                            ->columns(2),
                    ])
                    ->action(function (array $data, $record): void {
                        try {
                            app(OrdenCompraConformidadService::class)->registrarConformidadPorItems(
                                $record,
                                auth()->user(),
                                $data['items_conformidad'] ?? []
                            );

                            $hasRejected = collect($data['items_conformidad'] ?? [])
                                ->contains(fn (array $row): bool => strtoupper((string) ($row['decision'] ?? '')) === 'RECHAZADO');

                            if ($hasRejected) {
                                self::notifyReturnRequested($record);
                            }

                            Notification::make()
                                ->title('Conformidad registrada')
                                ->body('Se guardo la decision por item. Almacen debe procesar ENTRADA o REGISTRO NUEVO para los aceptados.')
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

                Action::make('procesarEntradaFinalPorItem')
                    ->label('Almacen: Entrada/Registro Nuevo')
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('primary')
                    ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                        && self::canProcessWarehouseEntryByItem($record))
                    ->form([
                        Repeater::make('items_entrada')
                            ->label('Ingreso por item aceptado')
                            ->default(fn ($record): array => self::buildEntradaRows($record))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('orden_compra_item_id')->required(),
                                TextInput::make('item')
                                    ->label('Item')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('descripcion')
                                    ->label('Descripcion')
                                    ->disabled()
                                    ->dehydrated(false),
                                Radio::make('modo')
                                    ->label('Accion final de almacen')
                                    ->options([
                                        'ENTRADA' => 'Entrada (producto existente)',
                                        'REGISTRO_NUEVO' => 'Registro Nuevo',
                                    ])
                                    ->required()
                                    ->live(),
                                Select::make('product_id')
                                    ->label('Producto existente')
                                    ->options(fn (): array => Product::query()
                                        ->orderBy('descripcion')
                                        ->limit(200)
                                        ->get()
                                        ->mapWithKeys(fn (Product $product): array => [
                                            $product->id => (string) ($product->sku . ' | ' . $product->descripcion),
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->required(fn (callable $get): bool => (string) ($get('modo') ?? '') === 'ENTRADA')
                                    ->visible(fn (callable $get): bool => (string) ($get('modo') ?? '') === 'ENTRADA'),
                            ])
                            ->columns(2),
                    ])
                    ->action(function (array $data, $record): void {
                        try {
                            app(OrdenCompraConformidadService::class)->procesarEntradaPorItems(
                                $record,
                                auth()->user(),
                                $data['items_entrada'] ?? []
                            );

                            Notification::make()
                                ->title('Entrada final procesada')
                                ->body('Se cerraron los items procesados en la solicitud original y se registro inventario.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo procesar la entrada por item')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('eliminarOdcHistorial')
                    ->label('Eliminar para Historial')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar ODC rechazada')
                    ->modalDescription('Esta accion elimina definitivamente la ODC rechazada del historial.')
                    ->visible(fn (): bool => false)
                    ->action(function ($record): void {
                        $correlativo = (string) ($record->correlativo_odc ?? ('#' . $record->id));

                        $record->delete();

                        Notification::make()
                            ->title('ODC eliminada del historial')
                            ->body('Se elimino la ODC ' . $correlativo . ' por estar rechazada.')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->label('Editar ODC')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn ($record, $livewire): bool => ! self::isPendingSumarioRecord($record)
                        && ! self::isHistorialOdcTab($livewire)
                        && ! self::isPagosOdcTab($livewire)
                        && ! self::isOdcCorreccionesTab($livewire)
                        && (! self::isOdcCorreccionesTab($livewire)
                            || ((string) ($record->estado ?? '') === 'RECHAZADA'
                                && in_array((string) ($record->rechazo_etapa ?? ''), ['gerencia_finanzas', 'validacion_finanzas'], true)))),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function makeAdministracionFacturaAction(): Action
    {
        return Action::make('registrarFacturaAdministracion')
            ->label(fn ($record): string => filled($record->factura_procesada_administracion_at)
                ? 'Administracion: Ver factura cargada'
                : 'Administracion: Registrar Factura')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('info')
            ->visible(fn ($record, $livewire): bool => ! self::isOdcCorreccionesTab($livewire)
                && self::canOpenManualInvoicePlaceholder($record))
            ->modalHeading(fn ($record): string => filled($record->factura_procesada_administracion_at)
                ? 'Factura cargada en DB'
                : 'Formulario contable de factura')
            ->fillForm(fn ($record): array => self::buildAdministracionFacturaData($record))
            ->form(self::administracionFacturaFormSchema())
            ->action(function (array $data, $record): void {
                try {
                    app(OrdenCompraAdministracionService::class)->registrarDatosFactura($record, auth()->user(), $data);

                    Notification::make()
                        ->title('Factura contable registrada')
                        ->body('Administracion completo la carga manual y el respaldo de retenciones.')
                        ->success()
                        ->send();

                    ActivityNotification::record(
                        auth()->user(),
                        'Factura registrada por Administracion',
                        'Se registro la carga contable de la ODC ' . (string) $record->correlativo_odc . '.',
                        'success'
                    );
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title('No se pudo registrar la factura')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function administracionFacturaFormSchema(): array
    {
        return [
            TextInput::make('factura_numero')
                ->label('Nro Factura')
                ->required()
                ->maxLength(255),
            TextInput::make('factura_numero_control')
                ->label('Nro Control')
                ->required()
                ->maxLength(255),
            DatePicker::make('factura_fecha_emision')
                ->label('Fecha de emision')
                ->required(),
            TextInput::make('factura_base_imponible')
                ->label('Base imponible')
                ->numeric()
                ->required(),
            TextInput::make('factura_monto_iva')
                ->label('Monto IVA')
                ->numeric()
                ->required(),
            TextInput::make('factura_monto_total')
                ->label('Monto total')
                ->numeric()
                ->required(),
            TextInput::make('retencion_iva_monto')
                ->label('Retencion IVA')
                ->numeric()
                ->default(0),
            TextInput::make('retencion_islr_monto')
                ->label('Retencion ISLR')
                ->numeric()
                ->default(0),
            FileUpload::make('comprobantes_retencion_paths')
                ->label('Comprobantes de retenciones')
                ->multiple()
                ->disk('public')
                ->directory('ordenes-compra/comprobantes-retencion')
                ->visibility('public')
                ->required(fn (callable $get): bool => (float) ($get('retencion_iva_monto') ?? 0) > 0 || (float) ($get('retencion_islr_monto') ?? 0) > 0),
            Textarea::make('observacion_administracion')
                ->label('Observaciones contables')
                ->rows(4),
        ];
    }

    public static function makeOpenFacturaRecepcionAction(): Action
    {
        return Action::make('abrirFacturaRecepcion')
            ->label('Descargar factura')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn ($record): bool => $record->hasFacturaRecepcion())
            ->url(fn ($record): ?string => $record->hasFacturaRecepcion()
                ? route('ordenes-compra.documento-recepcion.download', [
                    'ordenCompra' => $record,
                    'documento' => 'factura',
                ])
                : null)
            ->openUrlInNewTab();
    }

    public static function makeOpenNotaEntregaRecepcionAction(): Action
    {
        return Action::make('abrirNotaEntregaRecepcion')
            ->label('Descargar nota de entrega')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn ($record): bool => $record->hasNotaEntregaRecepcion())
            ->url(fn ($record): ?string => $record->hasNotaEntregaRecepcion()
                ? route('ordenes-compra.documento-recepcion.download', [
                    'ordenCompra' => $record,
                    'documento' => 'nota',
                ])
                : null)
            ->openUrlInNewTab();
    }

    public static function makeOpenFacturaImageAction(): Action
    {
        return self::makeOpenFacturaRecepcionAction();
    }

    public static function canAdministracionRegisterInvoice(mixed $record): bool
    {
        return self::canOpenManualInvoicePlaceholder($record);
    }

    public static function buildAdministracionFacturaData(mixed $record): array
    {
        return [
            'factura_numero' => (string) ($record->factura_numero ?? ''),
            'factura_numero_control' => (string) ($record->factura_numero_control ?? ''),
            'factura_fecha_emision' => $record->factura_fecha_emision,
            'factura_base_imponible' => $record->factura_base_imponible,
            'factura_monto_iva' => $record->factura_monto_iva,
            'factura_monto_total' => $record->factura_monto_total,
            'retencion_iva_monto' => $record->retencion_iva_monto,
            'retencion_islr_monto' => $record->retencion_islr_monto,
            'comprobantes_retencion_paths' => $record->comprobantes_retencion_paths ?? [],
            'observacion_administracion' => (string) ($record->observacion_administracion ?? ''),
        ];
    }

    private static function isCreationOdcTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'creacion_odc';
    }

    private static function isPagosOdcTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'pagos_odc';
    }

    private static function isHistorialOdcTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'historial_odc';
    }

    private static function isOdcCorreccionesTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'odc_en_correcciones';
    }

    private static function isCorreccionesOrHistorialOdcTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'odc_en_correcciones'
            || self::resolveActiveTab($livewire) === 'historial_odc';
    }

    private static function resolveActiveTab(mixed $livewire = null): string
    {
        $component = $livewire;

        if (! is_object($component)) {
            return '';
        }

        if (method_exists($component, 'getActiveTab')) {
            return (string) $component->getActiveTab();
        }

        if (property_exists($component, 'activeTab')) {
            return (string) ($component->activeTab ?? '');
        }

        return '';
    }

    private static function isPendingSumarioRecord(mixed $record): bool
    {
        return (int) ($record->is_sumario_pending_odc_row ?? 0) === 1;
    }

    private static function isFinalHistorialStage(mixed $record): bool
    {
        return in_array((string) ($record->workflow_post_compra ?? ''), ['BACKUP_FACTURA_COMPLETADO', 'CERRADA_CONFORME'], true);
    }

    private static function resolveHistorialEstado(string $workflow, string $estado): string
    {
        if (str_contains($workflow, 'RECHAZADA') || strtoupper($estado) === 'RECHAZADA') {
            return 'RECHAZADA';
        }

        return 'APROBADA';
    }

    private static function humanReadableSubEstado(string $workflow): string
    {
        return match ($workflow) {
            'PENDIENTE_PAGO_FINANZAS' => 'EN ESPERA DE PAGO',
            'PAGO_REGISTRADO_FINANZAS' => 'PAGO REGISTRADO',
            'PAGADO_Y_EN_TRANSITO' => 'PAGADO Y EN TRANSITO',
            'DOCUMENTO_RECEPCION_CARGADO_PROCURA' => 'DOC. DE RECEPCION CARGADO',
            'EN_TRANSICION_ALMACEN' => 'EN TRANSICION ALMACEN',
            'CONFORMIDAD_POR_ITEMS_COMPLETA' => 'CONFORMIDAD COMPLETA',
            'FACTURA_ENVIADA_ADMINISTRACION' => 'FACTURA ENVIADA A ADMINISTRACION',
            'BACKUP_FACTURA_COMPLETADO', 'CERRADA_CONFORME' => 'CERRADA CON EXITO',
            default => str_replace('_', ' ', $workflow ?: '-'),
        };
    }

    private static function subEstadoColor(string $workflow): string
    {
        return match ($workflow) {
            'PENDIENTE_PAGO_FINANZAS', 'DOCUMENTO_RECEPCION_CARGADO_PROCURA', 'EN_TRANSICION_ALMACEN' => 'warning',
            'PAGO_REGISTRADO_FINANZAS', 'PAGADO_Y_EN_TRANSITO', 'CONFORMIDAD_POR_ITEMS_COMPLETA' => 'info',
            'FACTURA_ENVIADA_ADMINISTRACION', 'BACKUP_FACTURA_COMPLETADO', 'CERRADA_CONFORME' => 'success',
            default => 'gray',
        };
    }

    private static function resolvePendingSumarioRecord(mixed $record): ?Sumario
    {
        if (! self::isPendingSumarioRecord($record)) {
            return null;
        }

        static $cache = [];

        $sumarioId = (int) ($record->id ?? 0);

        if ($sumarioId <= 0) {
            return null;
        }

        if (! array_key_exists($sumarioId, $cache)) {
            $cache[$sumarioId] = Sumario::query()
                ->with(['items.opciones', 'items.solicitudCompraItem.solicitudCompra', 'ordenesCompra', 'solicitudCompra'])
                ->find($sumarioId);
        }

        return $cache[$sumarioId];
    }

    private static function pendingOdcGroupsCountForTable(mixed $record): int
    {
        $sumario = self::resolvePendingSumarioRecord($record);

        if (! $sumario) {
            return 0;
        }

        $service = app(SumarioFinanceApprovalService::class);

        return $service->pendingProviderGroups($sumario)
            ->filter(fn (array $group): bool => ! $service->hasExistingGeneratedOrderForGroup($sumario, $group))
            ->count();
    }

    private static function selectedProviderTotalForTable(mixed $record, int $providerNumber): float
    {
        $sumario = self::resolvePendingSumarioRecord($record);

        if (! $sumario || ! in_array($providerNumber, [1, 2, 3], true)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($sumario->items ?? [] as $item) {
            $selectedOption = $item->opciones->firstWhere('seleccionada', true);

            if ((int) ($selectedOption?->opcion_numero ?? 0) !== $providerNumber) {
                continue;
            }

            $total += (float) ($selectedOption?->precio_total ?? 0);
        }

        return $total;
    }

    private static function humanReadableSumarioState(string $state): string
    {
        return match ($state) {
            'APROBADO_GERENCIA_FINANZAS' => 'PENDIENTE POR ORDENES DE COMPRA',
            default => str_replace('_', ' ', $state),
        };
    }

    private static function renderPendingOdcModalForRecord(mixed $record): string
    {
        $sumario = self::resolvePendingSumarioRecord($record);

        if (! $sumario) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No se encontro el sumario para generar ODC.</div>';
        }

        $service = app(SumarioFinanceApprovalService::class);

        $groups = $service->pendingProviderGroups($sumario)
            ->filter(fn (array $group): bool => ! $service->hasExistingGeneratedOrderForGroup($sumario, $group))
            ->values();

        if ($groups->isEmpty()) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No hay proveedores pendientes por generar en este sumario.</div>';
        }

        $providerSections = '';

        foreach ($groups as $group) {
            $providerName = e((string) ($group['provider_name'] ?: ('Proveedor #' . ($group['provider_id'] ?? 'N/A'))));
            $department = e((string) $group['departamento_solicitante']);
            $totalItems = (int) ($group['total_items'] ?? 0);

            $detailRows = self::renderProviderItemRowsForGroup($sumario, $group);

            if ($detailRows === '') {
                $detailRows = '<tr><td colspan="6" style="border:1px solid #d1d5db;padding:8px;color:#6b7280;">Sin items pendientes detectados para este proveedor.</td></tr>';
            }

            $generateUrl = route('ordenes-compra.generar-desde-sumario', [
                'sumario' => $sumario->id,
                'provider_id' => $group['provider_id'] ?? null,
                'provider_name' => $group['provider_name'] ?? null,
            ]);

            $form = '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
                . '<a href="' . e($generateUrl) . '" style="display:inline-block;border:1px solid #1d4ed8;background:#2563eb;color:white;border-radius:6px;padding:6px 10px;font-size:12px;text-decoration:none;">Realizar ODC para este Proveedor</a>'
                . '</div>';

            $providerSections .= '<div style="border:1px solid #d1d5db;border-radius:8px;overflow:hidden;margin-bottom:10px;">'
                . '<div style="padding:10px 12px;background:#f3f4f6;font-weight:600;">' . $providerName . ' | Dep: ' . $department . ' | Items: ' . $totalItems . '</div>'
                . '<div style="padding:10px;overflow:auto;">'
                . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
                . '<thead><tr style="background:#fafafa;">'
                . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
                . '<th style="border:1px solid #d1d5db;padding:8px;">Producto</th>'
                . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
                . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad</th>'
                . '<th style="border:1px solid #d1d5db;padding:8px;">P/U</th>'
                . '<th style="border:1px solid #d1d5db;padding:8px;">P/T</th>'
                . '</tr></thead><tbody>' . $detailRows . '</tbody></table>'
                . '</div>'
                . '<div style="padding:10px 12px;border-top:1px solid #e5e7eb;background:#fff;">' . $form . '</div>'
                . '</div>';
        }

        return '<div style="display:flex;flex-direction:column;gap:10px;">'
            . '<div style="padding:10px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;font-size:12px;"><strong>Sumario:</strong> ' . e((string) $sumario->correlativo_sdc) . ' | <strong>Solicitud:</strong> ' . e((string) ($sumario->solicitudCompra?->codigo_control ?: $sumario->solicitud_compra_id)) . '</div>'
            . '<div>' . $providerSections . '</div>'
            . '</div>';
    }

    private static function renderProviderItemRowsForGroup(Sumario $sumario, array $group): string
    {
        $rows = [];

        foreach ($sumario->items ?? [] as $item) {
            if ((string) ($item->sub_estado ?? '') === 'PENDIENTE_REVALIDACION_GERENCIA') {
                continue;
            }

            $resultado = (string) ($item->validacion_gerencia_resultado ?? '');

            if ($resultado !== '' && $resultado !== 'CORRECTO') {
                continue;
            }

            $selectedOption = $item->opciones->firstWhere('seleccionada', true);

            if (! $selectedOption) {
                continue;
            }

            $providerMatches = false;

            if (filled($group['provider_id'])) {
                $providerMatches = (int) ($selectedOption->proveedor_id ?? 0) === (int) $group['provider_id'];
            } else {
                $providerMatches = mb_strtolower(trim((string) ($selectedOption->proveedor_nombre ?? '')))
                    === mb_strtolower(trim((string) ($group['provider_name'] ?? '')));
            }

            if (! $providerMatches) {
                continue;
            }

            $department = trim((string) ($item->solicitudCompraItem?->solicitudCompra?->departamento_solicitante ?? $sumario->departamento_solicitante ?? 'SIN_DEPARTAMENTO'));

            if (mb_strtolower($department) !== mb_strtolower((string) ($group['departamento_solicitante'] ?? ''))) {
                continue;
            }

            $rows[] = '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->item ?: $item->id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->descripcion ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->unidad_medida ?? 'UND')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($item->cantidad ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($selectedOption->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($selectedOption->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '</tr>';
        }

        return implode('', $rows);
    }

    private static function renderSumarioSummaryModalForRecord(mixed $record): string
    {
        $sumario = self::resolvePendingSumarioRecord($record);

        if (! $sumario) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No se encontro el sumario.</div>';
        }

        return SumarioModalSummaryRenderer::render($sumario);
    }

    private static function renderSumarioSummaryModalForOdc(mixed $record): string
    {
        if (! $record || ! $record->sumario) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No se encontro el sumario asociado.</div>';
        }

        return SumarioModalSummaryRenderer::render($record->sumario);
    }

    private static function getSolicitudViewSchemaForOdc(): array
    {
        return [
            Section::make('Resumen de solicitud')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            TextInput::make('codigo_control')->label('N° Control Solicitud')->disabled()->columnSpan(1),
                            TextInput::make('estado')->label('Estado')->disabled()->columnSpan(1),
                            TextInput::make('fecha_solicitud')->label('Fecha')->disabled()->columnSpan(1),
                            TextInput::make('tipo_solicitud')->label('Tipo')->disabled()->columnSpan(1),
                            TextInput::make('prioridad')->label('Prioridad')->disabled()->columnSpan(1),
                            TextInput::make('departamento_solicitante')->label('Departamento')->disabled()->columnSpan(1),
                            TextInput::make('solicitado_por_nombre')->label('Solicitado por')->disabled()->columnSpan(2),
                            TextInput::make('por_almacen_nombre')->label('Almacén')->disabled()->columnSpan(2),
                            TextInput::make('aprobado_por_nombre')->label('Aprobador')->disabled()->columnSpan(1),
                            TextInput::make('recibido_por_nombre')->label('Procura')->disabled()->columnSpan(1),
                        ]),
                    Textarea::make('para_ser_usado_en')
                        ->label('Para ser usado en')
                        ->rows(2)
                        ->disabled(),
                    Grid::make(4)
                        ->schema([
                            TextInput::make('fecha_almacen')->label('Fecha almacén')->disabled(),
                            TextInput::make('fecha_aprobador')->label('Fecha aprobador')->disabled(),
                            TextInput::make('fecha_receptor')->label('Fecha procura')->disabled(),
                            TextInput::make('hora_receptor')->label('Hora procura')->disabled(),
                        ]),
                ]),

            Section::make('Materiales / servicios solicitados')
                ->schema([
                    Placeholder::make('items_detalle')
                        ->label('Items')
                        ->content(fn (callable $get): HtmlString => new HtmlString(self::renderSolicitudItemsTableForOdc($get('items') ?? [])))
                        ->dehydrated(false),
                ]),

            Section::make('Motivo de rechazo')
                ->visible(fn (callable $get): bool => filled($get('rechazo_comentario')))
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('rechazo_etapa')->label('Etapa')->disabled(),
                            TextInput::make('rechazo_por_nombre')->label('Rechazada por')->disabled(),
                            TextInput::make('rechazo_en')->label('Fecha rechazo')->disabled(),
                        ]),
                    Textarea::make('rechazo_comentario')->label('Comentario')->rows(3)->disabled(),
                ]),
        ];
    }

    private static function getSolicitudViewFormDataForOdc(mixed $record): array
    {
        $solicitud = $record?->sumario?->solicitudCompra;

        if (! $solicitud) {
            return [];
        }

        return [
            'id' => $solicitud->id,
            'codigo_control' => $solicitud->codigo_control ?: $solicitud->id,
            'fecha_solicitud' => $solicitud->fecha_solicitud?->format('d/m/Y'),
            'estado' => str_replace('_', ' ', (string) $solicitud->estado),
            'departamento_solicitante' => $solicitud->departamento_solicitante,
            'tipo_solicitud' => $solicitud->tipo_solicitud,
            'prioridad' => $solicitud->prioridad,
            'para_ser_usado_en' => $solicitud->para_ser_usado_en,
            'items' => $solicitud->items
                ->map(fn ($item) => [
                    'item' => $item->item,
                    'descripcion' => $item->descripcion,
                    'unidad_medida' => $item->unidad_medida,
                    'cantidad_solicitada' => $item->cantidad_solicitada,
                    'cantidad_existencia' => $item->cantidad_existencia,
                    'cantidad_a_comprar' => $item->cantidad_a_comprar,
                ])
                ->values()
                ->all(),
            'solicitado_por_nombre' => $solicitud->solicitadoPor?->name,
            'por_almacen_nombre' => $solicitud->porAlmacen?->name,
            'aprobado_por_nombre' => $solicitud->aprobadoPor?->name,
            'recibido_por_nombre' => $solicitud->recibidoPor?->name,
            'fecha_almacen' => $solicitud->fecha_almacen?->format('d/m/Y'),
            'fecha_aprobador' => $solicitud->fecha_aprobador?->format('d/m/Y'),
            'fecha_receptor' => $solicitud->fecha_receptor?->format('d/m/Y'),
            'hora_receptor' => $solicitud->hora_receptor,
            'rechazo_etapa' => $solicitud->rechazo_etapa ? strtoupper((string) $solicitud->rechazo_etapa) : null,
            'rechazo_por_nombre' => $solicitud->rechazoPor?->name,
            'rechazo_en' => $solicitud->rechazo_en?->format('d/m/Y H:i'),
            'rechazo_comentario' => $solicitud->rechazo_comentario,
        ];
    }

    private static function renderSolicitudItemsTableForOdc(array $items): string
    {
        if ($items === []) {
            return '<div style="padding:12px 0;color:#6b7280;">Sin items registrados.</div>';
        }

        $rows = collect($items)
            ->map(function ($item, int $index): string {
                $item = is_array($item) ? $item : [];

                return '<tr>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['item'] ?? ($index + 1))) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item['descripcion'] ?? '-')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['unidad_medida'] ?? '-')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) number_format((float) ($item['cantidad_solicitada'] ?? 0), 2, ',', '.')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) number_format((float) ($item['cantidad_existencia'] ?? 0), 2, ',', '.')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) number_format((float) ($item['cantidad_a_comprar'] ?? 0), 2, ',', '.')) . '</td>'
                    . '</tr>';
            })
            ->implode('');

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Unidad</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cant. solicitada</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cant. existencia</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cant. a comprar</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</div>';
    }

    private static function canUploadReceptionDocumentByProcura(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        return $user->can('ProcessReception:OrdenCompra')
            && filled($record->confirmado_procura_at)
            && blank($record->tipo_documento_recepcion)
            && ! filled($record->recepcion_procesada_at);
    }

    private static function canMarkTransitionByWarehouse(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        $departamento = strtoupper((string) ($user->departamento?->nombre ?? ''));
        $isWarehouse = str_contains($departamento, 'ALMAC');

        return $isWarehouse
            && filled($record->confirmado_procura_at)
            && filled($record->tipo_documento_recepcion)
            && blank($record->recepcion_procesada_at);
    }

    private static function canRegisterFinancePayment(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        return $user->can('Update:OrdenCompra')
            && (string) ($user->departamento?->nombre ?? '') === 'FINANZAS'
            && (string) ($record->workflow_post_compra ?? '') === 'PENDIENTE_PAGO_FINANZAS'
            && in_array((string) ($record->estado ?? ''), ['APROBADA', 'PAGADA', 'EN_ESPERA_DE_PRODUCTO'], true)
            && blank($record->pago_registrado_at);
    }

    private static function canApproveByGerenciaFinanzas(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        return $user->hasRole('Gerencia de Finanzas')
            && (string) ($record->workflow_post_compra ?? '') === 'PENDIENTE_APROBACION_GERENCIA_FINANZAS';
    }

    private static function canConfirmPaymentByProcura(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        return $user->can('ProcessReception:OrdenCompra')
            && filled($record->pago_registrado_at)
            && blank($record->confirmado_procura_at);
    }

    private static function canSendInvoiceToAdministration(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        $isFinanceDept = (string) ($user->departamento?->nombre ?? '') === 'FINANZAS';

        return $isFinanceDept
            && filled($record->factura_path)
            && (string) ($record->tipo_documento_recepcion ?? '') === 'FACTURA'
            && blank($record->factura_enviada_administracion_at);
    }

    private static function canOpenManualInvoicePlaceholder(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        $isAdministracion = (string) ($user->departamento?->nombre ?? '') === 'ADMINISTRACIÓN'
            || (string) ($user->departamento?->nombre ?? '') === 'ADMINISTRACION';

        return $isAdministracion
            && filled($record->factura_enviada_administracion_at);
    }

    private static function canRegisterItemConformity(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        $solicitanteId = (int) ($record->sumario?->solicitudCompra?->solicitado_por_user_id ?? 0);

        $hasPendingItems = $record->items()
            ->whereNull('decision_solicitante')
            ->exists();

        return $solicitanteId > 0
            && (int) $user->id === $solicitanteId
            && filled($record->recepcion_procesada_at)
            && $hasPendingItems;
    }

    private static function canProcessWarehouseEntryByItem(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || self::isPendingSumarioRecord($record)) {
            return false;
        }

        $departamento = strtoupper((string) ($user->departamento?->nombre ?? ''));
        $isWarehouse = str_contains($departamento, 'ALMAC');

        $hasAcceptedPending = $record->items()
            ->where('decision_solicitante', 'ACEPTADO')
            ->whereNull('procesado_almacen_at')
            ->exists();

        return $isWarehouse
            && filled($record->recepcion_procesada_at)
            && $hasAcceptedPending;
    }

    private static function buildConformidadRows(mixed $record): array
    {
        if (! $record) {
            return [];
        }

        return $record->items()
            ->orderBy('id')
            ->get()
            ->map(function ($item): array {
                $decision = (string) ($item->decision_solicitante ?? '');

                return [
                    'orden_compra_item_id' => $item->id,
                    'item' => (string) ($item->item ?? ('#' . $item->id)),
                    'descripcion' => (string) ($item->descripcion ?? ''),
                    'decision' => $decision === '' ? null : $decision,
                    'motivo' => (string) ($item->motivo_rechazo_solicitante ?? ''),
                ];
            })
            ->all();
    }

    private static function buildEntradaRows(mixed $record): array
    {
        if (! $record) {
            return [];
        }

        return $record->items()
            ->where('decision_solicitante', 'ACEPTADO')
            ->whereNull('procesado_almacen_at')
            ->orderBy('id')
            ->get()
            ->map(function ($item): array {
                return [
                    'orden_compra_item_id' => $item->id,
                    'item' => (string) ($item->item ?? ('#' . $item->id)),
                    'descripcion' => (string) ($item->descripcion ?? ''),
                    'modo' => null,
                    'product_id' => null,
                ];
            })
            ->all();
    }

    private static function notifyProcuraPaymentRegistered(mixed $record): void
    {
        $procuraRole = 'Procura';

        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', $procuraRole))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($record): void {
            $notification = Notification::make()
                ->title('Pago registrado por Finanzas')
                ->body('La ODC ' . (string) $record->correlativo_odc . ' ya tiene comprobante de pago. Procura debe confirmar y esperar producto.')
                ->success();

            \App\Support\Filament\DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
        });
    }

    private static function notifyAdministracionInvoiceReady(mixed $record): void
    {
        $departamentoId = Departamento::query()
            ->whereIn('nombre', ['ADMINISTRACIÓN', 'ADMINISTRACION'])
            ->value('id');

        $users = User::query()
            ->when($departamentoId, fn ($query) => $query->where('departamento_id', $departamentoId))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($record): void {
            $notification = Notification::make()
                ->title('Factura pendiente de carga manual')
                ->body('La ODC ' . (string) $record->correlativo_odc . ' fue enviada por Finanzas para respaldo contable en Administracion.')
                ->warning();

            \App\Support\Filament\DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
        });
    }

    private static function notifyFinanzasPaymentEnabled(mixed $record): void
    {
        $departamentoId = Departamento::query()
            ->where('nombre', 'FINANZAS')
            ->value('id');

        $users = User::query()
            ->when($departamentoId, fn ($query) => $query->where('departamento_id', $departamentoId))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($record): void {
            $notification = Notification::make()
                ->title('ODC aprobada para pago')
                ->body('La ODC ' . (string) $record->correlativo_odc . ' fue aprobada por Gerencia de Finanzas y esta lista para registrar pago.')
                ->warning();

            \App\Support\Filament\DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
        });
    }

    private static function notifyReturnRequested(mixed $record): void
    {
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Procura', 'Finanzas Pagos', 'Gerencia de Finanzas', 'Finanzas']))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($record): void {
            $notification = Notification::make()
                ->title('Solicitud de devolucion')
                ->body('El solicitante rechazo la ODC ' . (string) $record->correlativo_odc . '. Revisar gestion con proveedor.')
                ->danger();

            \App\Support\Filament\DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
        });
    }
}

