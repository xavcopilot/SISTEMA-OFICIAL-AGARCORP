<?php

namespace App\Filament\Resources\OrdenesCompra\Tables;

use App\Models\Departamento;
use App\Models\User;
use App\Support\ActivityNotification;
use App\Support\OrdenCompraConformidadService;
use App\Support\OrdenCompraRecepcionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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

                TextColumn::make('departamento_solicitante')
                    ->label('Departamento')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state)),

                TextColumn::make('workflow_post_compra')
                    ->label('Flujo post-compra')
                    ->badge()
                    ->state(fn ($record): string => (string) ($record->workflow_post_compra ?: 'PENDIENTE_PAGO_FINANZAS'))
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'PENDIENTE_PAGO_FINANZAS' => 'warning',
                        'PAGO_REGISTRADO_FINANZAS' => 'info',
                        'PAGO_CONFIRMADO_PROCURA', 'ESPERANDO_PRODUCTO' => 'info',
                        'EN_TRANSICION_ALMACEN' => 'warning',
                        'FACTURA_ENVIADA_ADMINISTRACION', 'BACKUP_FACTURA_COMPLETADO' => 'success',
                        'CERRADA_CONFORME' => 'success',
                        'RECHAZADA_SOLICITANTE' => 'danger',
                        default => 'gray',
                    }),

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

                Action::make('registrarPagoFinanzas')
                    ->label('Finanzas: Registrar Pago')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn ($record): bool => self::canRegisterFinancePayment($record))
                    ->form([
                        TextInput::make('monto_pagado')
                            ->label('Monto pagado')
                            ->numeric()
                            ->required(),
                        TextInput::make('referencia_pago')
                            ->label('Referencia / Nro operacion')
                            ->maxLength(255)
                            ->required(),
                        FileUpload::make('comprobante_pago_path')
                            ->label('Comprobante de pago')
                            ->image()
                            ->disk('public')
                            ->directory('ordenes-compra/comprobantes-pago')
                            ->visibility('public')
                            ->required(),
                        Textarea::make('observacion_pago')
                            ->label('Observacion')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record): void {
                        $record->forceFill([
                            'monto_pagado' => round((float) ($data['monto_pagado'] ?? 0), 2),
                            'referencia_pago' => (string) ($data['referencia_pago'] ?? ''),
                            'comprobante_pago_path' => $data['comprobante_pago_path'] ?? null,
                            'observacion_pago' => (string) ($data['observacion_pago'] ?? ''),
                            'pago_registrado_at' => now(),
                            'pago_por_user_id' => auth()->id(),
                            'estado' => 'PAGADA',
                            'workflow_post_compra' => 'PAGO_REGISTRADO_FINANZAS',
                        ])->save();

                        self::notifyProcuraPaymentRegistered($record);

                        Notification::make()
                            ->title('Pago registrado')
                            ->body('Finanzas registro el pago y notifico a Procura con el comprobante.')
                            ->success()
                            ->send();
                    }),

                Action::make('confirmarPagoProcura')
                    ->label('Procura: Confirmar pago recibido')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('info')
                    ->visible(fn ($record): bool => self::canConfirmPaymentByProcura($record))
                    ->action(function ($record): void {
                        $record->forceFill([
                            'confirmado_procura_at' => now(),
                            'confirmado_por_user_id' => auth()->id(),
                            'estado' => 'EN_ESPERA_DE_PRODUCTO',
                            'workflow_post_compra' => 'ESPERANDO_PRODUCTO',
                        ])->save();

                        Notification::make()
                            ->title('Pago confirmado por Procura')
                            ->body('La orden pasa a espera de llegada de producto.')
                            ->success()
                            ->send();
                    }),

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

                Action::make('enviarFacturaAdministracion')
                    ->label('Finanzas: Enviar factura a Administracion')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->visible(fn ($record): bool => self::canSendInvoiceToAdministration($record))
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

                Action::make('cargarFacturaManualPlaceholder')
                    ->label('Administracion: Cargar factura manual')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('info')
                    ->visible(fn ($record): bool => self::canOpenManualInvoicePlaceholder($record))
                    ->modalHeading('Carga manual de factura')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->form([
                        Placeholder::make('proximamente')
                            ->hiddenLabel()
                            ->content('Proximamente: formulario contable con campos de factura, retenciones y comprobantes.'),
                    ]),

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

                Action::make('rechazarConformidad')
                    ->label('Solicitante: Rechazar producto')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn ($record): bool => self::canRequestReturn($record))
                    ->form([
                        Textarea::make('devolucion_motivo')
                            ->label('Motivo de rechazo')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (array $data, $record): void {
                        $record->forceFill([
                            'devolucion_solicitada_at' => now(),
                            'devolucion_solicitada_por_user_id' => auth()->id(),
                            'devolucion_motivo' => (string) ($data['devolucion_motivo'] ?? ''),
                            'workflow_post_compra' => 'RECHAZADA_SOLICITANTE',
                        ])->save();

                        self::notifyReturnRequested($record);

                        Notification::make()
                            ->title('Rechazo registrado')
                            ->body('Se notifico a Procura y Finanzas para gestionar devolucion con proveedor.')
                            ->warning()
                            ->send();
                    }),

                Action::make('marcarFacturaProcesada')
                    ->label('Marcar Factura Procesada')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('info')
                    ->visible(fn ($record): bool => self::canMarkInvoiceProcessed($record))
                    ->action(function ($record): void {
                        $record->forceFill([
                            'factura_procesada_administracion_at' => now(),
                            'workflow_post_compra' => 'BACKUP_FACTURA_COMPLETADO',
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

        return $canOperate
            && filled($record->confirmado_procura_at)
            && ! filled($record->recepcion_procesada_at);
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
            && blank($record->devolucion_solicitada_at)
            && blank($record->conformidad_solicitante_at);
    }

    private static function canRegisterFinancePayment(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        return $user->can('Update:OrdenCompra')
            && blank($record->pago_registrado_at);
    }

    private static function canConfirmPaymentByProcura(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        return $user->can('ProcessReception:OrdenCompra')
            && filled($record->pago_registrado_at)
            && blank($record->confirmado_procura_at);
    }

    private static function canSendInvoiceToAdministration(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
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

        if (! $user || ! $record) {
            return false;
        }

        $isAdministracion = (string) ($user->departamento?->nombre ?? '') === 'ADMINISTRACIÓN'
            || (string) ($user->departamento?->nombre ?? '') === 'ADMINISTRACION';

        return $isAdministracion
            && filled($record->factura_enviada_administracion_at);
    }

    private static function canRequestReturn(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        $solicitanteId = (int) ($record->sumario?->solicitudCompra?->solicitado_por_user_id ?? 0);

        return $solicitanteId > 0
            && (int) $user->id === $solicitanteId
            && filled($record->recepcion_procesada_at)
            && blank($record->conformidad_solicitante_at)
            && blank($record->devolucion_solicitada_at);
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
            Notification::make()
                ->title('Pago registrado por Finanzas')
                ->body('La ODC ' . (string) $record->correlativo_odc . ' ya tiene comprobante de pago. Procura debe confirmar y esperar producto.')
                ->success()
                ->sendToDatabase($user);
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
            Notification::make()
                ->title('Factura pendiente de carga manual')
                ->body('La ODC ' . (string) $record->correlativo_odc . ' fue enviada por Finanzas para respaldo contable en Administracion.')
                ->warning()
                ->sendToDatabase($user);
        });
    }

    private static function notifyReturnRequested(mixed $record): void
    {
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Procura', 'Finanzas', 'Gerencia de Finanzas']))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($record): void {
            Notification::make()
                ->title('Solicitud de devolucion')
                ->body('El solicitante rechazo la ODC ' . (string) $record->correlativo_odc . '. Revisar gestion con proveedor.')
                ->danger()
                ->sendToDatabase($user);
        });
    }
}
