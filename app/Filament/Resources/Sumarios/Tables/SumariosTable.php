<?php

namespace App\Filament\Resources\Sumarios\Tables;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Support\ActivityNotification;
use App\Support\ControlCodeGenerator;
use App\Support\SolicitudCompraFlow;
use App\Support\SolicitudItemTrackingService;
use App\Support\SumarioModalSummaryRenderer;
use App\Support\SumarioFinanceApprovalService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SumariosTable
{
    private const SUBESTADO_PENDIENTE_REVALIDACION = 'PENDIENTE_REVALIDACION_GERENCIA';

    public static function configureForInspection(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('workflow_estado', 'PENDIENTE_VALIDACION_FINANZAS'))
            ->columns([
                TextColumn::make('correlativo_sdc')
                    ->label('Correlativo SDC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('solicitudCompra.codigo_control')
                    ->label('Solicitud asociada')
                    ->state(fn ($record): string => (string) ($record->solicitudCompra?->codigo_control ?: $record->solicitud_compra_id))
                    ->searchable(),

                TextColumn::make('fecha')
                    ->label('Fecha sumario')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('enviado_validacion_finanzas_at')
                    ->label('Enviado por Procura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record): string => (string) ($record->workflow_estado ?: $record->estado))
                    ->formatStateUsing(fn (?string $state): string => self::humanReadableWorkflowState((string) $state))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'PENDIENTE_VALIDACION_FINANZAS' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('verSumarioResumidoInspeccion')
                    ->label('Ver sumario')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Inspeccion | Sumario ' . (string) $record->correlativo_sdc)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderInspectionSummary($record))),

                Action::make('verSolicitudAsociadaInspeccion')
                    ->label('Ver solicitud asociada')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('info')
                    ->visible(fn ($record): bool => filled($record->solicitud_compra_id))
                    ->modalHeading(fn ($record): string => 'Solicitud asociada | #' . (string) ($record->solicitudCompra?->numero_solicitud_usuario ?: $record->solicitud_compra_id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn ($record): array => self::getSolicitudViewFormDataForCreation($record))
                    ->schema(self::getSolicitudViewSchemaForCreation()),

                Action::make('validarFinanzasAprobarInspeccion')
                    ->label('Enviar a Gerencia Finanzas')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->form([
                        TextInput::make('password')
                            ->label('Clave de firma')
                            ->password()
                            ->required(),
                        TextInput::make('password_confirmation')
                            ->label('Repetir clave de firma')
                            ->password()
                            ->required(),
                    ])
                    ->visible(fn ($record): bool => self::canValidateFinance($record)
                        && (string) ($record->workflow_estado ?? '') === 'PENDIENTE_VALIDACION_FINANZAS')
                    ->action(function (array $data, $record): void {
                        if (! self::validatePasswordForCreationModal($data)) {
                            return;
                        }

                        $record->forceFill([
                            'workflow_estado' => 'VALIDADO_FINANZAS',
                            'estado' => 'EN_ESPERA_APROBACION_GERENCIA',
                            'validado_finanzas_at' => now(),
                            'validado_por_user_id' => auth()->id(),
                            'validacion_finanzas_resultado' => 'APROBADO',
                            'validacion_finanzas_comentario' => null,
                        ])->save();

                        Notification::make()
                            ->title('Revision registrada')
                            ->body('El sumario fue revisado por Validador Finanzas y se envio a Gerencia de Finanzas.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Inspeccion de sumario aprobada',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue firmado por Validador Finanzas y enviado a Gerencia de Finanzas.',
                            'success'
                        );
                    }),

                Action::make('validarFinanzasRechazarInspeccion')
                    ->label('Rechazar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->form([
                        Textarea::make('comentario')
                            ->label('Motivo de rechazo')
                            ->required()
                            ->rows(4),
                    ])
                    ->visible(fn ($record): bool => self::canValidateFinance($record)
                        && (string) ($record->workflow_estado ?? '') === 'PENDIENTE_VALIDACION_FINANZAS')
                    ->action(function (array $data, $record): void {
                        $record->forceFill([
                            'workflow_estado' => 'RECHAZADO_VALIDACION_FINANZAS',
                            'validado_finanzas_at' => now(),
                            'validado_por_user_id' => auth()->id(),
                            'validacion_finanzas_resultado' => 'RECHAZADO',
                            'validacion_finanzas_comentario' => (string) ($data['comentario'] ?? ''),
                        ])->save();

                        Notification::make()
                            ->title('Rechazo registrado')
                            ->body('Procura vera este sumario en correccion para editar y volver a enviar.')
                            ->warning()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Inspeccion de sumario rechazada',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue rechazado por Validador Finanzas y retorno a Procura.',
                            'warning'
                        );
                    }),
            ])
            ->defaultSort('enviado_validacion_finanzas_at', 'desc');
    }

    public static function configureForManagementApproval(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereIn('workflow_estado', ['VALIDADO_FINANZAS']))
            ->columns([
                TextColumn::make('correlativo_sdc')
                    ->label('Correlativo SDC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('solicitudCompra.codigo_control')
                    ->label('Solicitud asociada')
                    ->state(fn ($record): string => (string) ($record->solicitudCompra?->codigo_control ?: $record->solicitud_compra_id))
                    ->searchable(),

                TextColumn::make('fecha')
                    ->label('Fecha sumario')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('validado_finanzas_at')
                    ->label('Validado por Finanzas')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record): string => (string) ($record->workflow_estado ?: $record->estado))
                    ->formatStateUsing(fn (?string $state): string => self::humanReadableWorkflowState((string) $state))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'VALIDADO_FINANZAS' => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('verSumarioAprobacionGerencia')
                    ->label('Ver sumario')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Aprobacion | Sumario ' . (string) $record->correlativo_sdc)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderInspectionSummary($record))),

                Action::make('verSolicitudAsociadaAprobacionGerencia')
                    ->label('Ver solicitud asociada')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('info')
                    ->visible(fn ($record): bool => filled($record->solicitud_compra_id))
                    ->modalHeading(fn ($record): string => 'Solicitud asociada | #' . (string) ($record->solicitudCompra?->numero_solicitud_usuario ?: $record->solicitud_compra_id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn ($record): array => self::getSolicitudViewFormDataForCreation($record))
                    ->schema(self::getSolicitudViewSchemaForCreation()),

                self::gerenciaFinanzasValidarItemsAction(true),
            ])
            ->defaultSort('validado_finanzas_at', 'desc');
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('correlativo_sdc')
                    ->label('Correlativo SDC')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('solicitudCompra.numero_solicitud_usuario')
                    ->label('N° solicitud')
                    ->state(fn ($record): string => (string) ($record->solicitudCompra?->numero_solicitud_usuario ?: $record->solicitud_compra_id))
                    ->sortable()
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitudCompra.codigo_control')
                    ->label('N° control')
                    ->state(fn ($record): string => (string) ($record->solicitudCompra?->codigo_control ?: $record->solicitud_compra_id))
                    ->sortable()
                    ->searchable()
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitudCompra.fecha_solicitud')
                    ->label('Fecha y hora')
                    ->state(fn ($record): string => self::formatSolicitudDateTime($record))
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitudCompra.para_ser_usado_en')
                    ->label('Para ser usado en')
                    ->searchable()
                    ->wrap()
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitudCompra.tipo_solicitud')
                    ->label('Tipo')
                    ->badge()
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitudCompra.prioridad')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state))
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitud_estado')
                    ->label('Estado')
                    ->state(fn ($record): string => str_replace('_', ' ', (string) ($record->solicitudCompra?->estado ?? '-')))
                    ->badge()
                    ->color(fn ($record): string => self::colorForSolicitudState((string) ($record->solicitudCompra?->estado ?? '')))
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitud_observacion')
                    ->label('Observación')
                    ->state(fn ($record): string => (string) ($record->solicitudCompra?->rechazo_comentario ?: '-'))
                    ->wrap()
                    ->visible(fn ($livewire): bool => self::isCreationTab($livewire)),

                TextColumn::make('solicitudCompra.codigo_control')
                    ->label('Solicitud')
                    ->state(fn ($record) => $record->solicitudCompra?->codigo_control ?: $record->solicitud_compra_id)
                    ->searchable()
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('procedencia')
                    ->label('Procedencia')
                    ->badge()
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('tipo_orden')
                    ->label('Tipo orden')
                    ->badge()
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('mensaje_pago_transito')
                    ->label('Mensaje dinamico')
                    ->state(fn ($record): string => self::buildPaidTransitMessage($record))
                    ->wrap()
                    ->toggleable()
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record, $livewire): string => self::historyFriendlyState((string) ($record->workflow_estado ?: $record->estado), $livewire))
                    ->formatStateUsing(fn (?string $state): string => self::humanReadableWorkflowState((string) $state))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'APROBADO' => 'success',
                        'RECHAZADO' => 'danger',
                        'BORRADOR' => 'gray',
                        'PENDIENTE_VALIDACION_FINANZAS' => 'warning',
                        'VALIDADO_FINANZAS' => 'info',
                        'APROBADO_GERENCIA_FINANZAS' => 'success',
                        'ODC_GENERADA' => 'success',
                        'RECHAZADO_VALIDACION_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS' => 'danger',
                        default => 'gray',
                    })
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('odc_faltantes')
                    ->label('ODC faltantes')
                    ->state(fn ($record): string => self::odcPendingCounterLabel($record))
                    ->badge()
                    ->color(fn ($record): string => self::odcPendingCounterColor($record))
                    ->visible(fn ($livewire): bool => self::isHistoryTab($livewire)),

                TextColumn::make('estado_creacion')
                    ->label('Estado de creación')
                    ->state(fn ($record): string => self::hasPendingItemsForCreation($record)
                        ? 'Pendiente de crear cotización'
                        : 'Sin pendientes')
                    ->badge()
                    ->color(fn ($record): string => self::hasPendingItemsForCreation($record) ? 'warning' : 'gray')
                    ->visible(false),

                TextColumn::make('total_compra_prov1')
                    ->label('Total Prov. 1')
                    ->state(fn ($record): float => self::resolveSelectedProviderTotalForColumn($record, 1))
                    ->money('USD')
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('total_compra_prov2')
                    ->label('Total Prov. 2')
                    ->state(fn ($record): float => self::resolveSelectedProviderTotalForColumn($record, 2))
                    ->money('USD')
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),

                TextColumn::make('total_compra_prov3')
                    ->label('Total Prov. 3')
                    ->state(fn ($record): float => self::resolveSelectedProviderTotalForColumn($record, 3))
                    ->money('USD')
                    ->visible(fn ($livewire): bool => ! self::isCreationTab($livewire)),
            ])
            ->recordActions([
                Action::make('verSolicitudCreacion')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn ($record, $livewire): bool => self::isCreationTab($livewire) && filled($record->solicitud_compra_id))
                    ->modalHeading(fn ($record): string => 'Solicitud #' . (string) ($record->solicitudCompra?->numero_solicitud_usuario ?: $record->solicitud_compra_id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn ($record): array => self::getSolicitudViewFormDataForCreation($record))
                    ->schema(self::getSolicitudViewSchemaForCreation())
                    ->extraModalFooterActions(fn ($record): array => self::approvalModalFooterActionsForCreation($record)),

                Action::make('verEstadoItemsCreacion')
                    ->label('Ver items')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('info')
                    ->visible(fn ($record, $livewire): bool => self::isCreationTab($livewire) && filled($record->solicitud_compra_id))
                    ->modalHeading(fn ($record): string => 'Estado de items | Solicitud #' . (string) ($record->solicitudCompra?->codigo_control ?: $record->solicitud_compra_id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderSolicitudItemsStatus($record))),

                Action::make('realizarSumarioDesdeCreacion')
                    ->label('Realizar sumario')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('success')
                    ->visible(fn ($record, $livewire): bool => self::isCreationTab($livewire)
                        && filled($record->solicitud_compra_id)
                        && self::hasPendingItemsForCreation($record))
                    ->url(fn ($record): string => route('filament.agarcorp.resources.sumarios.create', [
                        'solicitud_compra_id' => $record->solicitud_compra_id,
                    ])),

                Action::make('verComparativo')
                    ->label('Ver sumario')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn ($record, $livewire): bool => self::isCorrectionTab($livewire))
                    ->modalHeading(fn ($record): string => 'Sumario ' . (string) $record->correlativo_sdc)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record) => new HtmlString(self::renderInspectionSummary($record))),

                Action::make('verSumarioResumidoHistorial')
                    ->label('Ver sumario')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn ($record, $livewire): bool => self::isHistoryTab($livewire))
                    ->modalHeading(fn ($record): string => 'Resumen | Sumario ' . (string) $record->correlativo_sdc)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record) => new HtmlString(self::renderInspectionSummary($record))),

                Action::make('verSolicitudAsociadaHistorial')
                    ->label('Ver solicitud asociada')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('info')
                    ->visible(fn ($record, $livewire): bool => self::isHistoryTab($livewire) && filled($record->solicitud_compra_id))
                    ->modalHeading(fn ($record): string => 'Solicitud asociada | #' . (string) ($record->solicitudCompra?->numero_solicitud_usuario ?: $record->solicitud_compra_id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn ($record): array => self::getSolicitudViewFormDataForCreation($record))
                    ->schema(self::getSolicitudViewSchemaForCreation()),

                Action::make('sumarioCorreccion')
                    ->label('Sumario en correccion')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('warning')
                    ->modalHeading(fn ($record): string => 'Correccion inteligente | Sumario ' . (string) $record->correlativo_sdc)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderCorrectionBoard($record)))
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canUseCorrectionBoard($record)),

                Action::make('enviarValidacionFinanzas')
                    ->label('Enviar a Validacion Finanzas')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->form([
                        TextInput::make('password')
                            ->label('Clave de firma')
                            ->password()
                            ->required(),
                        TextInput::make('password_confirmation')
                            ->label('Repetir clave de firma')
                            ->password()
                            ->required(),
                    ])
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canSubmitForFinanceValidation($record))
                    ->action(function (array $data, $record): void {
                        if (! self::validatePasswordForCreationModal($data)) {
                            return;
                        }

                        $record->forceFill([
                            'workflow_estado' => 'PENDIENTE_VALIDACION_FINANZAS',
                            'enviado_validacion_finanzas_at' => now(),
                            'enviado_por_user_id' => auth()->id(),
                            'validado_finanzas_at' => null,
                            'validado_por_user_id' => null,
                            'validacion_finanzas_resultado' => null,
                            'validacion_finanzas_comentario' => null,
                            'decision_gerencia_finanzas_at' => null,
                            'decision_gerencia_por_user_id' => null,
                            'decision_gerencia_resultado' => null,
                            'decision_gerencia_comentario' => null,
                        ])->save();

                        Notification::make()
                            ->title('Sumario enviado')
                            ->body('Se envio al analista/validador de Finanzas para revision.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Sumario enviado a validacion',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue enviado a Validacion Finanzas.',
                            'success'
                        );
                    }),

                Action::make('validarFinanzasAprobar')
                    ->label('Validar Finanzas: Aceptar')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canValidateFinance($record)
                        && (string) ($record->workflow_estado ?? '') === 'PENDIENTE_VALIDACION_FINANZAS')
                    ->action(function ($record): void {
                        $record->forceFill([
                            'workflow_estado' => 'VALIDADO_FINANZAS',
                            'estado' => 'EN_ESPERA_APROBACION_GERENCIA',
                            'validado_finanzas_at' => now(),
                            'validado_por_user_id' => auth()->id(),
                            'validacion_finanzas_resultado' => 'APROBADO',
                            'validacion_finanzas_comentario' => null,
                        ])->save();

                        Notification::make()
                            ->title('Validacion registrada')
                            ->body('El sumario fue aceptado por Finanzas y pasa a Gerencia de Finanzas.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Validacion de Finanzas aprobada',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue aprobado en Validacion Finanzas.',
                            'success'
                        );
                    }),

                Action::make('validarFinanzasRechazar')
                    ->label('Validar Finanzas: Rechazar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->form([
                        Textarea::make('comentario')
                            ->label('Motivo de rechazo')
                            ->required()
                            ->rows(4),
                    ])
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canValidateFinance($record)
                        && (string) ($record->workflow_estado ?? '') === 'PENDIENTE_VALIDACION_FINANZAS')
                    ->action(function (array $data, $record): void {
                        $record->forceFill([
                            'workflow_estado' => 'RECHAZADO_VALIDACION_FINANZAS',
                            'validado_finanzas_at' => now(),
                            'validado_por_user_id' => auth()->id(),
                            'validacion_finanzas_resultado' => 'RECHAZADO',
                            'validacion_finanzas_comentario' => (string) ($data['comentario'] ?? ''),
                        ])->save();

                        Notification::make()
                            ->title('Rechazo registrado')
                            ->body('Finanzas rechazo el sumario con comentario para Procura.')
                            ->warning()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Validacion de Finanzas rechazada',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue rechazado en Validacion Finanzas.',
                            'warning'
                        );
                    }),

                self::gerenciaFinanzasValidarItemsAction(),

                Action::make('generarOdcs')
                    ->label('Generar ODC por proveedor')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('success')
                    ->url(fn (): string => OrdenCompraResource::getUrl('index'))
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canGenerateOdcs($record))
                    ->openUrlInNewTab(false),

                Action::make('enviarCorregidoGerenciaFila')
                    ->label('Enviar a Gerencia Finanzas')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar sumario corregido a Gerencia Finanzas')
                    ->modalDescription('Solo se habilita cuando todos los items rechazados ya fueron corregidos.')
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canUseCorrectionBoard($record)
                        && self::canSendCorrectedSumarioToGerencia($record))
                    ->action(function ($record): void {
                        $sumario = Sumario::query()->find($record->id);

                        if (! $sumario) {
                            Notification::make()
                                ->title('Sumario no encontrado')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! self::canSendCorrectedSumarioToGerencia($sumario)) {
                            Notification::make()
                                ->title('Aun hay items pendientes por corregir')
                                ->body('Corrija todos los items rechazados antes de enviar el sumario a Gerencia Finanzas.')
                                ->warning()
                                ->send();

                            return;
                        }

                        self::sendCorrectedSumarioToGerencia($sumario);
                    }),

                EditAction::make()
                    ->label('Editar Sumario')
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canEditDraftOrRejected($record)),

                DeleteAction::make()
                    ->visible(fn ($record, $livewire): bool => ! self::isCreationTab($livewire)
                        && ! self::isHistoryTab($livewire)
                        && self::canDeleteDraft($record)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function gerenciaFinanzasValidarItemsAction(bool $forApprovalModule = false): Action
    {
        return Action::make($forApprovalModule ? 'gerenciaFinanzasValidarItemsAprobacion' : 'gerenciaFinanzasValidarItems')
            ->label('Aprobacion de Sumario')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalWidth('7xl')
            ->form([
                Hidden::make('sumario_encabezado_html')
                    ->dehydrated(false),

                Placeholder::make('sumario_encabezado_preview')
                    ->label('Encabezado')
                    ->content(fn (callable $get): HtmlString => new HtmlString((string) ($get('sumario_encabezado_html') ?: '<div style="padding:8px;color:#6b7280;">Sin datos del encabezado.</div>')))
                    ->dehydrated(false),

                Repeater::make('items_revision')
                    ->label('Revision de items (Correcto / X)')
                    ->default([])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->schema([
                        Hidden::make('sumario_item_id'),
                        Hidden::make('comparativo_html')
                            ->dehydrated(false),

                        Placeholder::make('comparativo_preview')
                            ->label('Cuadro comparativo del item')
                            ->content(fn (callable $get): HtmlString => new HtmlString((string) ($get('comparativo_html') ?: '<div style="padding:8px;color:#6b7280;">Sin datos comparativos.</div>')))
                            ->dehydrated(false)
                            ->columnSpan(11),

                        Select::make('resultado')
                            ->label('Decision Gerencia')
                            ->options([
                                'CORRECTO' => '✅ Correcto',
                                'RECHAZADO' => '❌ Incorrecto',
                            ])
                            ->required()
                            ->live()
                            ->columnSpan(3),
                    ])
                    ->columns(14),

                Hidden::make('sumario_pie_html')
                    ->dehydrated(false),

                Placeholder::make('sumario_pie_preview')
                    ->label('Pie del formato')
                    ->content(fn (callable $get): HtmlString => new HtmlString((string) ($get('sumario_pie_html') ?: '<div style="padding:8px;color:#6b7280;">Sin datos del pie del formato.</div>')))
                    ->dehydrated(false),

                Radio::make('error_formato')
                    ->label('Hay algun error en formato?')
                    ->options([
                        'SI' => 'Si',
                        'NO' => 'No',
                    ])
                    ->default('NO')
                    ->required()
                    ->inline()
                    ->live(),
                Textarea::make('comentario_gerencia')
                    ->label('Comentario general (obligatorio si marca alguna X o detecta error de formato)')
                    ->rows(3)
                    ->live()
                    ->visible(fn (callable $get): bool => collect($get('items_revision') ?? [])
                        ->contains(fn ($row): bool => is_array($row) && (string) ($row['resultado'] ?? '') === 'RECHAZADO')
                        || (string) ($get('error_formato') ?? 'NO') === 'SI')
                    ->required(fn (callable $get): bool => collect($get('items_revision') ?? [])
                        ->contains(fn ($row): bool => is_array($row) && (string) ($row['resultado'] ?? '') === 'RECHAZADO')
                        || (string) ($get('error_formato') ?? 'NO') === 'SI'),
            ])
            ->modalSubmitActionLabel('Enviar a Procura')
            ->visible(fn ($record, $livewire): bool => ($forApprovalModule || (! self::isCreationTab($livewire) && ! self::isHistoryTab($livewire)))
                && self::canGerenciaFinanceDecision($record)
                && in_array((string) ($record->workflow_estado ?? ''), ['VALIDADO_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS'], true))
            ->fillForm(fn ($record): array => [
                'sumario_encabezado_html' => self::renderGerenciaHeaderSummary($record),
                'sumario_pie_html' => self::renderGerenciaFooterSummary($record),
                'error_formato' => 'NO',
                'items_revision' => self::buildGerenciaItemRevisionPayload($record),
            ])
            ->action(function (array $data, $record): void {
                $rows = collect($data['items_revision'] ?? [])
                    ->filter(fn ($row): bool => is_array($row) && filled($row['sumario_item_id'] ?? null))
                    ->values();

                if ($rows->isEmpty()) {
                    Notification::make()
                        ->title('Sin items para validar')
                        ->danger()
                        ->send();

                    return;
                }

                $hasRejectedRows = $rows->contains(fn (array $row): bool => (string) ($row['resultado'] ?? '') === 'RECHAZADO');
                $hasFormatError = (string) ($data['error_formato'] ?? 'NO') === 'SI';
                $generalComment = trim((string) ($data['comentario_gerencia'] ?? ''));

                if (($hasRejectedRows || $hasFormatError) && $generalComment === '') {
                    Notification::make()
                        ->title('Comentario requerido')
                        ->body('Debes registrar un comentario general cuando marques una X o indiques error de formato.')
                        ->danger()
                        ->send();

                    return;
                }

                DB::transaction(function () use ($record, $rows, $data, $generalComment, $hasFormatError): void {
                    $sumario = Sumario::query()->lockForUpdate()->findOrFail($record->id);

                    foreach ($rows as $row) {
                        SumarioItem::query()
                            ->where('sumario_id', $sumario->id)
                            ->whereKey((int) $row['sumario_item_id'])
                            ->update([
                                'validacion_gerencia_resultado' => (string) ($row['resultado'] ?? 'CORRECTO'),
                                'validacion_gerencia_comentario' => (string) ($row['resultado'] ?? '') === 'RECHAZADO'
                                    ? $generalComment
                                    : null,
                                'sub_estado' => (string) ($row['resultado'] ?? '') === 'RECHAZADO'
                                    ? 'RECHAZADO_GERENCIA'
                                    : 'PENDIENTE_OC',
                            ]);
                    }

                    $correctCount = $rows->where('resultado', 'CORRECTO')->count();
                    $rejectedCount = $rows->where('resultado', 'RECHAZADO')->count();

                    if ($hasFormatError) {
                        $workflow = 'RECHAZADO_GERENCIA_FINANZAS';
                        $resultado = 'RECHAZADO';
                    } elseif ($correctCount === 0) {
                        $workflow = 'RECHAZADO_GERENCIA_FINANZAS';
                        $resultado = 'RECHAZADO';
                    } elseif ($rejectedCount > 0) {
                        $workflow = 'RECHAZADO_GERENCIA_FINANZAS';
                        $resultado = 'RECHAZADO';
                    } else {
                        $workflow = 'APROBADO_GERENCIA_FINANZAS';
                        $resultado = 'APROBADO';
                    }

                    $sumario->forceFill([
                        'workflow_estado' => $workflow,
                        'decision_gerencia_finanzas_at' => now(),
                        'decision_gerencia_por_user_id' => auth()->id(),
                        'decision_gerencia_resultado' => $resultado,
                        'decision_gerencia_comentario' => trim((string) ($data['comentario_gerencia'] ?? '')),
                    ])->save();
                });

                Notification::make()
                    ->title('Validacion de Gerencia registrada')
                    ->body('La revision por item fue guardada y el sumario regreso a Procura para generar ODC solo con items Correctos.')
                    ->success()
                    ->send();

                ActivityNotification::record(
                    auth()->user(),
                    'Validacion por item de Gerencia',
                    'Gerencia de Finanzas valido por item el sumario ' . (string) $record->correlativo_sdc . '.',
                    'success'
                );
            });
    }

    private static function formatSolicitudDateTime(mixed $record): string
    {
        $fecha = $record->solicitudCompra?->fecha_receptor ?: $record->solicitudCompra?->fecha_solicitud;
        $hora = (string) ($record->solicitudCompra?->hora_receptor ?? '');

        if (! $fecha) {
            return '-';
        }

        $formattedDate = $fecha->format('d/m/Y');

        return trim($formattedDate . ' ' . $hora);
    }

    private static function colorForSolicitudState(string $state): string
    {
        return match ($state) {
            'RECIBIDO_POR_PROCURA' => 'info',
            'COMPLETADA' => 'success',
            'RECHAZADA' => 'danger',
            default => 'gray',
        };
    }

    private static function renderSolicitudItemsStatus(mixed $record): string
    {
        $solicitudId = (int) ($record->solicitud_compra_id ?? 0);

        if ($solicitudId <= 0) {
            return '<div style="padding:12px 0;color:#6b7280;">La solicitud no tiene items asociados.</div>';
        }

        $items = SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->orderBy('item')
            ->orderBy('id')
            ->get(['id', 'item', 'descripcion', 'unidad_medida', 'cantidad_solicitada', 'cantidad_a_comprar', 'cantidad_pedida', 'cantidad_en_sumario']);

        if ($items->isEmpty()) {
            return '<div style="padding:12px 0;color:#6b7280;">La solicitud no tiene items registrados.</div>';
        }

        $rows = $items->map(function (SolicitudCompraItem $item): string {
            $meta = (float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0);
            $incluida = (float) ($item->cantidad_en_sumario ?? 0);
            $faltante = max(0, $meta - $incluida);
            $estado = $faltante > 0 ? 'Pendiente' : 'Incluido completo';
            $estadoColor = $faltante > 0 ? '#92400e' : '#166534';
            $estadoBg = $faltante > 0 ? '#fef3c7' : '#dcfce7';

            return '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item->item ?: $item->id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->descripcion ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item->unidad_medida ?? 'UND')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format($meta, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format($incluida, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format($faltante, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;"><span style="display:inline-block;padding:2px 8px;border-radius:9999px;background:' . $estadoBg . ';color:' . $estadoColor . ';font-weight:600;">' . e($estado) . '</span></td>'
                . '</tr>';
        })->implode('');

        $totalItems = $items->count();
        $completos = $items->filter(function (SolicitudCompraItem $item): bool {
            $meta = (float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0);
            $incluida = (float) ($item->cantidad_en_sumario ?? 0);

            return $incluida >= $meta;
        })->count();
        $pendientes = $totalItems - $completos;

        $summary = '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px;">'
            . '<div style="padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;"><strong>Total items:</strong> ' . $totalItems . '</div>'
            . '<div style="padding:8px 10px;border:1px solid #bbf7d0;border-radius:8px;background:#f0fdf4;"><strong>Completos:</strong> ' . $completos . '</div>'
            . '<div style="padding:8px 10px;border:1px solid #fde68a;border-radius:8px;background:#fffbeb;"><strong>Pendientes:</strong> ' . $pendientes . '</div>'
            . '</div>';

        return $summary
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripción</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Meta</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Incluido en sumarios</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Faltante</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</div>';
    }

    private static function approvalModalFooterActionsForCreation(mixed $record): array
    {
        $solicitud = self::resolveSolicitudFromRecord($record);

        if (! $solicitud) {
            return [];
        }

        return [
            Action::make('firmarAlmacenDesdeVerSolicitudCreacion')
                ->label('Firmar almacén')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('warning')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $solicitud->fresh()))
                ->schema(self::signatureSchemaForCreation())
                ->action(function (array $data) use ($solicitud): void {
                    self::signAlmacenFromCreationModal($solicitud, $data);
                }),

            Action::make('rechazarAlmacenDesdeVerSolicitudCreacion')
                ->label('Rechazar almacén')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $solicitud->fresh()))
                ->schema(self::rejectionSchemaForCreation())
                ->action(function (array $data) use ($solicitud): void {
                    self::rejectFromCreationModal($solicitud, $data, 'almacen');
                }),

            Action::make('firmarAprobacionDesdeVerSolicitudCreacion')
                ->label('Firmar aprobación')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignApprover(auth()->user(), $solicitud->fresh()))
                ->schema(self::approverSignatureSchemaForCreation())
                ->action(function (array $data) use ($solicitud): void {
                    self::signApproverFromCreationModal($solicitud, $data);
                }),

            Action::make('rechazarAprobacionDesdeVerSolicitudCreacion')
                ->label('Rechazar aprobación')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignApprover(auth()->user(), $solicitud->fresh()))
                ->schema(self::rejectionSchemaForCreation())
                ->action(function (array $data) use ($solicitud): void {
                    self::rejectFromCreationModal($solicitud, $data, 'aprobador');
                }),

            Action::make('firmarProcuraDesdeVerSolicitudCreacion')
                ->label('Firmar recepción procura')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('info')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignProcura(auth()->user(), $solicitud->fresh()))
                ->schema(self::procuraSignatureSchemaForCreation())
                ->action(function (array $data) use ($solicitud): void {
                    self::signProcuraFromCreationModal($solicitud, $data);
                }),

            Action::make('rechazarProcuraDesdeVerSolicitudCreacion')
                ->label('Rechazar procura')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignProcura(auth()->user(), $solicitud->fresh()))
                ->schema(self::rejectionSchemaForCreation())
                ->action(function (array $data) use ($solicitud): void {
                    self::rejectFromCreationModal($solicitud, $data, 'procura');
                }),
        ];
    }

    private static function resolveSolicitudFromRecord(mixed $record): ?SolicitudCompra
    {
        if (! $record || ! filled($record->solicitud_compra_id)) {
            return null;
        }

        return SolicitudCompra::query()->find((int) $record->solicitud_compra_id);
    }

    private static function getSolicitudViewSchemaForCreation(): array
    {
        return [
            Section::make('Resumen de solicitud')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            TextInput::make('codigo_control')->label('N° control')->disabled()->columnSpan(1),
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
                        ->content(fn (callable $get): HtmlString => new HtmlString(self::renderSolicitudItemsTableForCreation($get('items') ?? [])))
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

    private static function getSolicitudViewFormDataForCreation(mixed $record): array
    {
        $solicitud = self::resolveSolicitudFromRecord($record);

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

    private static function renderSolicitudItemsTableForCreation(array $items): string
    {
        if ($items === []) {
            return '<div style="padding:12px 0;color:#6b7280;">Sin items registrados.</div>';
        }

        $rows = collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item, int $index): string {
                return '<tr>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['item'] ?? $index + 1)) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item['descripcion'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['unidad_medida'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) ($item['cantidad_solicitada'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) ($item['cantidad_existencia'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) ($item['cantidad_a_comprar'] ?? '')) . '</td>'
                    . '</tr>';
            })
            ->implode('');

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Solicitada</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Existencia</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">A comprar</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</div>';
    }

    private static function signatureSchemaForCreation(): array
    {
        return [
            TextInput::make('password')->label('Clave de firma')->password()->required(),
            TextInput::make('password_confirmation')->label('Repetir clave de firma')->password()->required(),
        ];
    }

    private static function approverSignatureSchemaForCreation(): array
    {
        return [
            Select::make('prioridad')
                ->label('Prioridad')
                ->options([
                    'Alta' => 'Alta',
                    'Media' => 'Media',
                    'Baja' => 'Baja',
                ])
                ->required(),
            ...self::signatureSchemaForCreation(),
        ];
    }

    private static function procuraSignatureSchemaForCreation(): array
    {
        return [
            Select::make('crear_sumario_ahora')
                ->label('¿Deseas realizar el sumario ahora?')
                ->options([
                    'SI' => 'Sí, abrir pestaña Creación de Sumarios en Sumarios Cotizaciones',
                    'NO' => 'No, continuar luego',
                ])
                ->default('NO')
                ->required(),
            ...self::signatureSchemaForCreation(),
        ];
    }

    private static function rejectionSchemaForCreation(): array
    {
        return [
            Textarea::make('comentario_rechazo')->label('Comentario de rechazo')->rows(4)->required()->maxLength(2000),
            ...self::signatureSchemaForCreation(),
        ];
    }

    private static function signAlmacenFromCreationModal(SolicitudCompra $solicitud, array $data): void
    {
        $record = $solicitud->fresh();

        if (! SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record) || ! self::validatePasswordForCreationModal($data)) {
            return;
        }

        $record->forceFill([
            'por_almacen_user_id' => $record->por_almacen_user_id ?: auth()->id(),
            'cargo_almacen' => auth()->user()?->cargo?->nombre,
            'fecha_almacen' => now()->toDateString(),
            'estado' => SolicitudCompra::ESTADO_EN_ESPERA_APROBADOR,
        ])->save();

        Notification::make()->title('Firma de almacén registrada')->success()->send();

        ActivityNotification::record(
            auth()->user(),
            'Firma de almacen registrada',
            'Se firmo en etapa almacen la solicitud #' . (string) $record->id . '.',
            'success'
        );
    }

    private static function signApproverFromCreationModal(SolicitudCompra $solicitud, array $data): void
    {
        $record = $solicitud->fresh();

        if (! SolicitudCompraFlow::canSignApprover(auth()->user(), $record) || ! self::validatePasswordForCreationModal($data)) {
            return;
        }

        $prioridad = (string) ($data['prioridad'] ?? '');
        if (! in_array($prioridad, ['Alta', 'Media', 'Baja'], true)) {
            Notification::make()->title('Prioridad requerida')->body('Debes seleccionar la prioridad antes de firmar la aprobacion.')->danger()->send();
            return;
        }

        $record->forceFill([
            'aprobado_por_user_id' => $record->aprobado_por_user_id ?: auth()->id(),
            'cargo_aprobador' => auth()->user()?->cargo?->nombre,
            'prioridad' => $prioridad,
            'fecha_aprobador' => now()->toDateString(),
            'estado' => SolicitudCompra::ESTADO_EN_ESPERA_PROCURA,
        ])->save();

        Notification::make()->title('Aprobación registrada')->success()->send();

        ActivityNotification::record(
            auth()->user(),
            'Aprobacion registrada',
            'Se firmo en etapa aprobacion la solicitud #' . (string) $record->id . '.',
            'success'
        );
    }

    private static function signProcuraFromCreationModal(SolicitudCompra $solicitud, array $data): void
    {
        $record = $solicitud->fresh();

        if (! SolicitudCompraFlow::canSignProcura(auth()->user(), $record) || ! self::validatePasswordForCreationModal($data)) {
            return;
        }

        $record->forceFill([
            'recibido_por_user_id' => $record->recibido_por_user_id ?: auth()->id(),
            'cargo_receptor' => auth()->user()?->cargo?->nombre,
            'codigo_control_procura' => $record->codigo_control_procura ?: ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
            'fecha_receptor' => now()->toDateString(),
            'hora_receptor' => now()->format('H:i:s'),
            'estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA,
        ])->save();

        Notification::make()->title('Recepción de procura registrada')->success()->send();

        ActivityNotification::record(
            auth()->user(),
            'Recepcion de procura registrada',
            'Se firmo en etapa procura la solicitud #' . (string) $record->id . '.',
            'success'
        );

        if ((string) ($data['crear_sumario_ahora'] ?? 'NO') === 'SI' && self::hasPendingItemsForSumario($record)) {
            self::ensureDraftSumarioForSolicitud($record);
        }
    }

    private static function rejectFromCreationModal(SolicitudCompra $solicitud, array $data, string $etapa): void
    {
        $record = $solicitud->fresh();

        $canReject = match ($etapa) {
            'almacen' => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record),
            'aprobador' => SolicitudCompraFlow::canSignApprover(auth()->user(), $record),
            'procura' => SolicitudCompraFlow::canSignProcura(auth()->user(), $record),
            default => false,
        };

        if (! $canReject || ! self::validatePasswordForCreationModal($data)) {
            return;
        }

        $comentario = trim((string) ($data['comentario_rechazo'] ?? ''));

        if ($comentario === '') {
            return;
        }

        $destinatarioUserId = $record->solicitado_por_user_id;

        $record->forceFill([
            'estado' => 'RECHAZADA',
            'rechazo_etapa' => $etapa,
            'rechazo_comentario' => $comentario,
            'rechazo_por_user_id' => auth()->id(),
            'rechazo_destinatario_user_id' => $destinatarioUserId,
            'rechazo_en' => now(),
        ])->save();

        $destinatario = $destinatarioUserId ? User::query()->find($destinatarioUserId) : null;

        if ($destinatario) {
            $rechazadoPor = auth()->user()?->name ?? 'Usuario';

            Notification::make()
                ->title('Solicitud rechazada en etapa ' . strtoupper($etapa))
                ->body('Solicitud #' . $record->id . ' rechazada por ' . $rechazadoPor . '. Motivo: ' . $comentario)
                ->danger()
                ->sendToDatabase($destinatario);
        }

        Notification::make()
            ->title('Rechazo registrado')
            ->body('Se notifico al solicitante con el comentario de rechazo.')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Rechazo registrado',
            'Se rechazo la solicitud #' . (string) $record->id . ' en etapa ' . strtoupper($etapa) . '.',
            'warning'
        );
    }

    private static function validatePasswordForCreationModal(array $data): bool
    {
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if ($password === '' || $password !== $passwordConfirmation) {
            Notification::make()
                ->title('Verificacion fallida')
                ->body('Debes escribir la misma clave de firma dos veces antes de enviar.')
                ->danger()
                ->send();

            return false;
        }

        $signatureHash = auth()->user()?->firma_password ?: auth()->user()?->password ?: '';

        if (Hash::check($password, $signatureHash)) {
            return true;
        }

        Notification::make()
            ->title('Clave incorrecta')
            ->body('La firma no se registro porque la clave de firma no coincide.')
            ->danger()
            ->send();

        return false;
    }

    private static function canEditDraftOrRejected(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || ! $user->can('Update:Sumario')) {
            return false;
        }

        return in_array((string) ($record->workflow_estado ?? 'BORRADOR'), [
            'BORRADOR',
            'RECHAZADO_VALIDACION_FINANZAS',
            'RECHAZADO_GERENCIA_FINANZAS',
        ], true);
    }

    private static function hasPendingItemsForCreation(mixed $record): bool
    {
        if (! $record || ! $record->solicitud_compra_id) {
            return false;
        }

        return (bool) $record->solicitudCompra?->items()
            ->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)')
            ->exists();
    }

    private static function isCreationTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'creacion_sumarios';
    }

    private static function isCorrectionTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'en_correccion';
    }

    private static function isHistoryTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'sumarios';
    }

    private static function historyFriendlyState(string $state, mixed $livewire = null): string
    {
        if (! self::isHistoryTab($livewire)) {
            return $state;
        }

        return match ($state) {
            'APROBADO_GERENCIA_FINANZAS', 'ODC_GENERADA' => 'APROBADO',
            'RECHAZADO_GERENCIA_FINANZAS' => 'RECHAZADO',
            default => $state,
        };
    }

    private static function humanReadableWorkflowState(string $state): string
    {
        return match ($state) {
            'VALIDADO_FINANZAS' => 'EN ESPERA DE APROBACION GERENCIA',
            'APROBADO_GERENCIA_FINANZAS' => 'PENDIENTE POR ORDENES DE COMPRA',
            default => str_replace('_', ' ', $state),
        };
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

    private static function canDeleteDraft(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || ! $user->can('Delete:Sumario')) {
            return false;
        }

        return (string) ($record->workflow_estado ?? 'BORRADOR') === 'BORRADOR'
            && blank($record->ordenesCompra()->first());
    }

    private static function canSubmitForFinanceValidation(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! $user->can('SubmitValidation:Sumario')) {
            return false;
        }

        return in_array((string) ($record->workflow_estado ?? 'BORRADOR'), [
            'BORRADOR',
            'ODC_GENERADA',
            'RECHAZADO_VALIDACION_FINANZAS',
        ], true)
            && blank($record->ordenesCompra()->first());
    }

    private static function canValidateFinance(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        return $user->can('ValidateFinance:Sumario')
            && ! $user->can('ApprovePayment:Sumario');
    }

    private static function canGerenciaFinanceDecision(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        return $user->can('ApprovePayment:Sumario');
    }

    private static function canGenerateOdcs(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! $user->can('GenerateOdcs:Sumario')) {
            return false;
        }

        $workflow = (string) ($record->workflow_estado ?? '');

        if ($workflow !== 'APROBADO_GERENCIA_FINANZAS') {
            return false;
        }

        return self::pendingOdcGroupsCount($record) > 0;
    }

    private static function pendingOdcGroupsCount(mixed $record): int
    {
        $sumario = $record instanceof Sumario
            ? $record->loadMissing(['items.opciones', 'items.solicitudCompraItem.solicitudCompra', 'ordenesCompra'])
            : Sumario::query()
                ->with(['items.opciones', 'items.solicitudCompraItem.solicitudCompra', 'ordenesCompra'])
                ->find((int) ($record->id ?? 0));

        if (! $sumario) {
            return 0;
        }

        $service = app(SumarioFinanceApprovalService::class);

        return $service->pendingProviderGroups($sumario)
            ->filter(function (array $group) use ($sumario): bool {
                $query = $sumario->ordenesCompra()->where('departamento_solicitante', (string) $group['departamento_solicitante']);

                if (filled($group['provider_id'])) {
                    $query->where('proveedor_id', (int) $group['provider_id']);
                }

                return ! $query->exists();
            })
            ->count();
    }

    private static function odcPendingCounterLabel(mixed $record): string
    {
        $workflow = (string) ($record->workflow_estado ?? '');

        if (! in_array($workflow, ['APROBADO_GERENCIA_FINANZAS', 'ODC_GENERADA'], true)) {
            return '-';
        }

        $sumario = $record instanceof Sumario
            ? $record->loadMissing(['items.opciones', 'items.solicitudCompraItem.solicitudCompra', 'ordenesCompra'])
            : Sumario::query()
                ->with(['items.opciones', 'items.solicitudCompraItem.solicitudCompra', 'ordenesCompra'])
                ->find((int) ($record->id ?? 0));

        if (! $sumario) {
            return '-';
        }

        $service = app(SumarioFinanceApprovalService::class);
        $totalGroups = $service->pendingProviderGroups($sumario)->count();

        if ($totalGroups <= 0) {
            return 'Completo';
        }

        $pending = self::pendingOdcGroupsCount($sumario);

        return 'Faltan ' . $pending . ' de ' . $totalGroups;
    }

    private static function odcPendingCounterColor(mixed $record): string
    {
        $label = self::odcPendingCounterLabel($record);

        if ($label === 'Completo') {
            return 'success';
        }

        if (str_starts_with($label, 'Faltan')) {
            return 'warning';
        }

        return 'gray';
    }

    private static function canUseCorrectionBoard(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! $user->can('Update:Sumario')) {
            return false;
        }

        return in_array((string) ($record->workflow_estado ?? ''), [
            'RECHAZADO_GERENCIA_FINANZAS',
            'APROBADO_GERENCIA_FINANZAS',
        ], true);
    }

    private static function renderCorrectionBoard(mixed $record): string
    {
        $sumario = $record->loadMissing(['items.opciones']);

        $validos = $sumario->items->filter(function ($item): bool {
            return (string) ($item->validacion_gerencia_resultado ?? '') === 'CORRECTO'
                && (string) ($item->sub_estado ?? '') !== self::SUBESTADO_PENDIENTE_REVALIDACION;
        })->values();

        $rechazados = $sumario->items->filter(function ($item): bool {
            $resultado = (string) ($item->validacion_gerencia_resultado ?? '');
            $subEstado = (string) ($item->sub_estado ?? '');

            return $resultado === 'RECHAZADO' || $subEstado === self::SUBESTADO_PENDIENTE_REVALIDACION;
        })->values();

        $head = '<div style="margin-bottom:12px;padding:10px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">'
            . '<strong>Regla activa:</strong> Si hay items rechazados o pendientes de revalidacion, no se genera ODC hasta nueva decision de Gerencia. '
            . 'Los items del grupo rechazado/correccion se gestionan desde "Editar Sumario" sin bloquear el Grupo A.'
            . '</div>';

        $rowsA = $validos->map(function ($item): string {
            $opcionSeleccionada = $item->opciones->firstWhere('seleccionada', true);
            $precioUnitarioSeleccionado = (float) ($opcionSeleccionada?->precio_unitario ?? 0);
            $precioTotalSeleccionado = (float) ($opcionSeleccionada?->precio_total ?? 0);

            return '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->item ?: $item->id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $item->descripcion) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) $item->cantidad, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format($precioUnitarioSeleccionado, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format($precioTotalSeleccionado, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">Correcto</td>'
                . '</tr>';
        })->implode('');

        if ($rowsA === '') {
            $rowsA = '<tr><td colspan="6" style="border:1px solid #d1d5db;padding:8px;">No hay items validados actualmente.</td></tr>';
        }

        $rowsB = $rechazados->map(function ($item): string {
            $estadoCorreccion = (string) ($item->sub_estado ?? '') === self::SUBESTADO_PENDIENTE_REVALIDACION
                ? 'Pendiente de revalidacion'
                : 'X (Rechazado)';
            $opcionSeleccionada = $item->opciones->firstWhere('seleccionada', true);
            $precioUnitarioSeleccionado = (float) ($opcionSeleccionada?->precio_unitario ?? 0);
            $precioTotalSeleccionado = (float) ($opcionSeleccionada?->precio_total ?? 0);

            return '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->item ?: $item->id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $item->descripcion) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) $item->cantidad, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format($precioUnitarioSeleccionado, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format($precioTotalSeleccionado, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e($estadoCorreccion) . '</td>'
                . '</tr>';
        })->implode('');

        if ($rowsB === '') {
            $rowsB = '<tr><td colspan="6" style="border:1px solid #d1d5db;padding:8px;">No hay items rechazados o en correccion.</td></tr>';
        }

        $comentarioGeneral = trim((string) ($sumario->decision_gerencia_comentario ?? ''));

        if ($comentarioGeneral === '') {
            $comentarioGeneral = (string) ($sumario->items
                ->first(fn ($item): bool => filled($item->validacion_gerencia_comentario ?? null))?->validacion_gerencia_comentario ?? '');
        }

        $comentarioGeneralBlock = '<div style="border:1px solid #d1d5db;border-radius:8px;overflow:hidden;">'
            . '<div style="padding:8px 10px;background:#eef2ff;font-weight:700;">Comentario general de Gerencia</div>'
            . '<div style="padding:10px;">' . nl2br(e($comentarioGeneral !== '' ? $comentarioGeneral : 'Sin comentario general registrado.')) . '</div>'
            . '</div>';

        return $head
            . '<div style="display:grid;grid-template-columns:1fr;gap:12px;">'
            . '<div style="overflow:auto;">'
            . '<div style="font-weight:700;margin-bottom:6px;">Grupo A | Items Validados</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed;">'
            . '<colgroup>'
            . '<col style="width:11%;">'
            . '<col style="width:29%;">'
            . '<col style="width:14%;">'
            . '<col style="width:14%;">'
            . '<col style="width:14%;">'
            . '<col style="width:18%;">'
            . '</colgroup>'
            . '<thead><tr style="background:#ecfdf5;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado</th>'
            . '</tr></thead><tbody>' . $rowsA . '</tbody></table>'
            . '</div>'
            . '<div style="overflow:auto;">'
            . '<div style="font-weight:700;margin-bottom:6px;">Grupo B | Rechazados y en Correccion</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed;">'
            . '<colgroup>'
            . '<col style="width:11%;">'
            . '<col style="width:29%;">'
            . '<col style="width:14%;">'
            . '<col style="width:14%;">'
            . '<col style="width:14%;">'
            . '<col style="width:18%;">'
            . '</colgroup>'
            . '<thead><tr style="background:#fff7ed;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado</th>'
            . '</tr></thead><tbody>' . $rowsB . '</tbody></table>'
            . '</div>'
                . $comentarioGeneralBlock
            . '</div>';
    }

    private static function canSendCorrectedSumarioToGerencia(Sumario $sumario): bool
    {
        $items = SumarioItem::query()
            ->where('sumario_id', $sumario->id)
            ->get(['validacion_gerencia_resultado', 'sub_estado']);

        $hasPendingRevalidation = $items->contains(
            fn ($item): bool => (string) ($item->sub_estado ?? '') === self::SUBESTADO_PENDIENTE_REVALIDACION
        );

        $hasUnresolvedRejected = $items->contains(function ($item): bool {
            return (string) ($item->validacion_gerencia_resultado ?? '') === 'RECHAZADO'
                && (string) ($item->sub_estado ?? '') !== self::SUBESTADO_PENDIENTE_REVALIDACION;
        });

        return $hasPendingRevalidation && ! $hasUnresolvedRejected;
    }

    private static function sendCorrectedSumarioToGerencia(Sumario $sumario): void
    {
        $sumario->forceFill([
            'workflow_estado' => 'VALIDADO_FINANZAS',
            'estado' => 'EN_ESPERA_APROBACION_GERENCIA',
            'decision_gerencia_finanzas_at' => null,
            'decision_gerencia_por_user_id' => null,
            'decision_gerencia_resultado' => 'PENDIENTE_REVALIDACION',
            'decision_gerencia_comentario' => null,
        ])->save();

        Notification::make()
            ->title('Sumario enviado a Gerencia Finanzas')
            ->body('El sumario corregido quedo en espera de aprobacion de Gerencia Finanzas.')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Sumario corregido enviado a Gerencia',
            'El sumario ' . (string) $sumario->correlativo_sdc . ' fue enviado por Procura a Gerencia Finanzas luego de correcciones.',
            'success'
        );
    }

    private static function refreshWorkflowAfterCorrection(Sumario $sumario): void
    {
        $sumario = $sumario->fresh()->load('items');

        $items = $sumario->items;

        $hasCorrect = $items->contains(fn ($item): bool => (string) ($item->validacion_gerencia_resultado ?? '') === 'CORRECTO');
        $hasRejected = $items->contains(fn ($item): bool => (string) ($item->validacion_gerencia_resultado ?? '') === 'RECHAZADO');
        $hasPendingRevalidation = $items->contains(fn ($item): bool => (string) ($item->sub_estado ?? '') === self::SUBESTADO_PENDIENTE_REVALIDACION);

        $workflow = (string) ($sumario->workflow_estado ?? '');
        $decision = (string) ($sumario->decision_gerencia_resultado ?? '');

        if ($hasRejected) {
            $workflow = 'RECHAZADO_GERENCIA_FINANZAS';
            $decision = 'RECHAZADO';
        } elseif ($hasPendingRevalidation) {
            $workflow = 'RECHAZADO_GERENCIA_FINANZAS';
            $decision = 'RECHAZADO';
        } elseif ($hasCorrect) {
            $workflow = 'APROBADO_GERENCIA_FINANZAS';
            $decision = 'APROBADO';
        }

        $sumario->forceFill([
            'workflow_estado' => $workflow,
            'decision_gerencia_resultado' => $decision,
        ])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildGerenciaItemRevisionPayload(mixed $record): array
    {
        $sumario = $record->loadMissing('items.opciones');

        return $sumario->items
            ->map(function ($item): array {
                return [
                    'sumario_item_id' => $item->id,
                    'comparativo_html' => self::renderGerenciaComparativoItem($item),
                    'resultado' => (string) ($item->validacion_gerencia_resultado ?: 'CORRECTO'),
                    'comentario' => (string) ($item->validacion_gerencia_comentario ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    private static function renderGerenciaComparativoItem(SumarioItem $item): string
    {
        $opciones = $item->opciones->keyBy('opcion_numero');
        $selectedOption = $item->opciones->firstWhere('seleccionada', true);
        $selectedOptionNumber = (int) ($selectedOption?->opcion_numero ?? 0);

        $renderOption = function (int $optionNumber) use ($opciones, $selectedOptionNumber): string {
            $option = $opciones->get($optionNumber);
            $isSelected = $selectedOptionNumber === $optionNumber;
            $cellStyle = $isSelected
                ? 'border:1px solid #86efac;padding:4px;background:#dcfce7;font-size:10px;line-height:1.2;'
                : 'border:1px solid #d1d5db;padding:4px;font-size:10px;line-height:1.2;';

            return '<td style="' . $cellStyle . '">' . e((string) ($option?->proveedor_nombre ?? '-')) . '</td>'
                . '<td style="' . $cellStyle . '">' . e((string) ($option?->marca ?? '-')) . '</td>'
                . '<td style="' . $cellStyle . 'text-align:right;">' . number_format((float) ($option?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="' . $cellStyle . 'text-align:right;">' . number_format((float) ($option?->precio_total ?? 0), 2, ',', '.') . '</td>';
        };

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:10px;table-layout:auto;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Cant</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Prov 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Marca 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/U 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/T 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Prov 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Marca 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/U 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/T 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Prov 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Marca 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/U 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/T 3</th>'
            . '</tr></thead>'
            . '<tbody><tr>'
            . '<td style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">' . e((string) ($item->item ?: $item->id)) . '</td>'
            . '<td style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">' . e((string) $item->descripcion) . '</td>'
            . '<td style="border:1px solid #d1d5db;padding:3px;text-align:center;white-space:nowrap;">' . e((string) ($item->unidad_medida ?? 'UND')) . '</td>'
            . '<td style="border:1px solid #d1d5db;padding:3px;text-align:right;white-space:nowrap;">' . number_format((float) $item->cantidad, 2, ',', '.') . '</td>'
            . $renderOption(1)
            . $renderOption(2)
            . $renderOption(3)
            . '</tr></tbody></table>'
            . '</div>';
    }

    private static function renderInspectionSummary(mixed $record): string
    {
        return SumarioModalSummaryRenderer::render($record);
    }

    private static function renderGerenciaHeaderSummary(mixed $record): string
    {
        return SumarioModalSummaryRenderer::renderHeader($record);
    }

    private static function renderGerenciaFooterSummary(mixed $record): string
    {
        return SumarioModalSummaryRenderer::renderFooter($record);
    }

    /**
     * @return array<int, float>
     */
    private static function resolveSelectedProviderTotals(mixed $sumario): array
    {
        return SumarioModalSummaryRenderer::selectedProviderTotals($sumario);
    }

    private static function resolveSelectedProviderTotalForColumn(mixed $sumario, int $providerNumber): float
    {
        return SumarioModalSummaryRenderer::selectedProviderTotalForColumn($sumario, $providerNumber);
    }

    /**
     * @return array<int, string>
     */
    private static function resolveProviderColumnNames(mixed $sumario): array
    {
        return SumarioModalSummaryRenderer::providerColumnNames($sumario);
    }

    private static function buildPaidTransitMessage(mixed $record): string
    {
        $sumario = $record->loadMissing(['ordenesCompra.items']);

        $segments = [];

        foreach ($sumario->ordenesCompra as $ordenCompra) {
            if ((string) ($ordenCompra->workflow_post_compra ?? '') !== 'PAGADO_Y_EN_TRANSITO') {
                continue;
            }

            $itemsText = $ordenCompra->items
                ->map(function ($item): string {
                    $itemNumero = (string) ($item->item ?: $item->id);

                    return '#' . $itemNumero . ' ' . (string) $item->descripcion;
                })
                ->implode(', ');

            if ($itemsText === '') {
                continue;
            }

            $segments[] = 'Los siguientes items de la OC ' . (string) $ordenCompra->id . ' fueron pagados y estan en transito: ' . $itemsText;
        }

        return $segments === [] ? 'Sin items pagados.' : implode(' | ', $segments);
    }
}
