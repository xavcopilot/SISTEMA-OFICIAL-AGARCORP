<?php

namespace App\Filament\Resources\SolicitudesCompra\Tables;

use App\Filament\Resources\AprobacionesCompra\AprobacionesCompraResource;
use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Filament\Resources\Sumarios\SumarioResource;
use App\Models\Sumario;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\User;
use App\Support\ActivityNotification;
use App\Support\ControlCodeGenerator;
use App\Support\SolicitudCompraFlow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;

class SolicitudesCompraTable
{
    /** @var array<int, array{label:string,color:string}> */
    private static array $generalStateCache = [];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_solicitud_usuario')
                    ->label('N° solicitud')
                    ->state(fn ($record) => $record->numero_solicitud_usuario ?: $record->id)
                    ->sortable(),

                TextColumn::make('codigo_control')
                    ->label('N° control')
                    ->state(fn ($record) => $record->codigo_control ?: $record->id)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('para_ser_usado_en')
                    ->label('Para ser usado en')
                    ->searchable(),

                TextColumn::make('tipo_solicitud')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('prioridad')
                    ->label('Prioridad')
                    ->badge(),

                TextColumn::make('estado_general')
                    ->label('Estado')
                    ->state(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['label'])
                    ->badge()
                    ->color(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['color']),
            ])
            ->recordActions([
                Action::make('imprimirPdf')
                    ->label('Imprimir / Guardar PDF')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->visible(fn (SolicitudCompra $record): bool => ! SolicitudCompraFlow::isDraft($record))
                    ->url(fn ($record) => route('solicitudes-compra.formato.print', ['solicitudCompra' => $record]))
                    ->openUrlInNewTab(),
                Action::make('verDetalle')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (SolicitudCompra $record): string => 'Solicitud #' . ($record->numero_solicitud_usuario ?: $record->id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn (SolicitudCompra $record): array => self::getViewFormData($record))
                    ->schema(self::getViewSchema()),

                Action::make('trazabilidadSolicitud')
                    ->label('Trazabilidad')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('info')
                    ->visible(fn (SolicitudCompra $record): bool => filled($record->fecha_receptor))
                    ->modalHeading(fn (SolicitudCompra $record): string => 'Trazabilidad solicitud ' . (string) ($record->codigo_control ?: $record->id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->schema([
                        Placeholder::make('trazabilidad_detalle')
                            ->hiddenLabel()
                            ->content(fn (SolicitudCompra $record): HtmlString => new HtmlString(self::renderTrackingView($record)))
                            ->dehydrated(false),
                    ]),

                Action::make('conformidadMateriales')
                    ->label('Conformidad de Materiales')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (SolicitudCompra $record): bool => self::hasPendingConformidadForSolicitante($record))
                    ->url(fn (SolicitudCompra $record): ?string => ($ordenCompraId = self::firstPendingConformidadOrdenCompraId($record))
                        ? OrdenCompraResource::getUrl('edit', ['record' => $ordenCompraId])
                        : null),

                EditAction::make()
                    ->authorize(fn ($record): bool => SolicitudCompraFlow::canEditRequest(auth()->user(), $record))
                    ->visible(fn ($record): bool => SolicitudCompraFlow::canEditRequest(auth()->user(), $record)),

                DeleteAction::make()
                    ->authorize(fn ($record): bool => SolicitudCompraFlow::canDeleteRequest(auth()->user(), $record))
                    ->visible(fn ($record): bool => SolicitudCompraFlow::canDeleteRequest(auth()->user(), $record)),
            ])
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc');
    }

    private static function hasPendingConformidadForSolicitante(SolicitudCompra $record): bool
    {
        $userId = (int) (auth()->id() ?? 0);

        if ($userId <= 0 || (int) ($record->solicitado_por_user_id ?? 0) !== $userId) {
            return false;
        }

        return self::firstPendingConformidadOrdenCompraId($record) !== null;
    }

    private static function firstPendingConformidadOrdenCompraId(SolicitudCompra $record): ?int
    {
        $ordenCompraId = OrdenCompra::query()
            ->whereHas('sumario', fn ($query) => $query->where('solicitud_compra_id', $record->id))
            ->whereNotNull('recepcion_procesada_at')
            ->whereHas('items', fn ($query) => $query->whereNull('decision_solicitante'))
            ->orderByDesc('id')
            ->value('id');

        return $ordenCompraId ? (int) $ordenCompraId : null;
    }

    private static function getViewSchema(): array
    {
        return [
            Section::make('Resumen de solicitud')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            TextInput::make('codigo_control')
                                ->label('N° control')
                                ->disabled()
                                ->columnSpan(1),

                            TextInput::make('estado')
                                ->label('Estado')
                                ->disabled()
                                ->columnSpan(1),

                            TextInput::make('fecha_solicitud')
                                ->label('Fecha')
                                ->disabled()
                                ->columnSpan(1),

                            TextInput::make('tipo_solicitud')
                                ->label('Tipo')
                                ->disabled()
                                ->columnSpan(1),

                            TextInput::make('prioridad')
                                ->label('Prioridad')
                                ->disabled()
                                ->columnSpan(1),

                            TextInput::make('departamento_solicitante')
                                ->label('Departamento')
                                ->disabled()
                                ->columnSpan(1),

                            TextInput::make('solicitado_por_nombre')
                                ->label('Solicitado por')
                                ->disabled()
                                ->columnSpan(2),

                            TextInput::make('por_almacen_nombre')
                                ->label('Almacén')
                                ->disabled()
                                ->columnSpan(2),

                            TextInput::make('aprobado_por_nombre')
                                ->label('Aprobador')
                                ->disabled()
                                ->columnSpan(1),

                            TextInput::make('recibido_por_nombre')
                                ->label('Procura')
                                ->disabled()
                                ->columnSpan(1),
                        ]),

                    Textarea::make('para_ser_usado_en')
                        ->label('Para ser usado en')
                        ->rows(2)
                        ->disabled(),

                    Grid::make(4)
                        ->schema([
                            TextInput::make('fecha_almacen')
                                ->label('Fecha almacén')
                                ->disabled(),

                            TextInput::make('fecha_aprobador')
                                ->label('Fecha aprobador')
                                ->disabled(),

                            TextInput::make('fecha_receptor')
                                ->label('Fecha procura')
                                ->disabled(),

                            TextInput::make('hora_receptor')
                                ->label('Hora procura')
                                ->disabled(),
                        ]),
                ]),

            Section::make('Materiales / servicios solicitados')
                ->schema([
                    Placeholder::make('items_detalle')
                        ->label('Items')
                        ->content(fn (callable $get) => new HtmlString(self::renderItemsView($get('items') ?? [])))
                        ->dehydrated(false),
                ]),

            Section::make('Motivo de rechazo')
                ->description('Esta solicitud fue rechazada. Corrigela y vuelve a enviarla para continuar el flujo.')
                ->visible(fn (callable $get): bool => filled($get('rechazo_comentario')))
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('rechazo_etapa')
                                ->label('Etapa')
                                ->disabled(),
                            TextInput::make('rechazo_por_nombre')
                                ->label('Rechazada por')
                                ->disabled(),
                            TextInput::make('rechazo_en')
                                ->label('Fecha rechazo')
                                ->disabled(),
                        ]),

                    Textarea::make('rechazo_comentario')
                        ->label('Comentario')
                        ->rows(3)
                        ->disabled(),
                ]),
        ];
    }

    private static function getViewFormData(SolicitudCompra $record): array
    {
        return [
            'id' => $record->id,
            'codigo_control' => $record->codigo_control ?: $record->id,
            'fecha_solicitud' => $record->fecha_solicitud?->format('d/m/Y'),
            'estado' => str_replace('_', ' ', (string) $record->estado),
            'departamento_solicitante' => $record->departamento_solicitante,
            'tipo_solicitud' => $record->tipo_solicitud,
            'prioridad' => $record->prioridad,
            'para_ser_usado_en' => $record->para_ser_usado_en,
            'items' => $record->items
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
            'solicitado_por_nombre' => $record->solicitadoPor?->name,
            'por_almacen_nombre' => $record->porAlmacen?->name,
            'aprobado_por_nombre' => $record->aprobadoPor?->name,
            'recibido_por_nombre' => $record->recibidoPor?->name,
            'cargo_solicitante' => $record->cargo_solicitante,
            'cargo_almacen' => $record->cargo_almacen,
            'cargo_aprobador' => $record->cargo_aprobador,
            'cargo_receptor' => $record->cargo_receptor,
            'fecha_solicitante' => $record->fecha_solicitante?->format('d/m/Y'),
            'fecha_almacen' => $record->fecha_almacen?->format('d/m/Y'),
            'fecha_aprobador' => $record->fecha_aprobador?->format('d/m/Y'),
            'fecha_receptor' => $record->fecha_receptor?->format('d/m/Y'),
            'hora_receptor' => $record->hora_receptor,
            'rechazo_etapa' => $record->rechazo_etapa ? strtoupper((string) $record->rechazo_etapa) : null,
            'rechazo_por_nombre' => $record->rechazoPor?->name,
            'rechazo_en' => $record->rechazo_en?->format('d/m/Y H:i'),
            'rechazo_comentario' => $record->rechazo_comentario,
        ];
    }

    private static function renderItemsView(array $items): string
    {
        if ($items === []) {
            return '<div style="padding:12px 0;color:#6b7280;">Sin items registrados.</div>';
        }

        $rows = collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item, int $index): string {
                $itemNumber = e((string) ($item['item'] ?? $index + 1));
                $descripcion = e((string) ($item['descripcion'] ?? ''));
                $unidad = e((string) ($item['unidad_medida'] ?? ''));
                $solicitada = e((string) ($item['cantidad_solicitada'] ?? ''));
                $existencia = e((string) ($item['cantidad_existencia'] ?? ''));
                $comprar = e((string) ($item['cantidad_a_comprar'] ?? ''));

                return '<tr>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . $itemNumber . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;">' . $descripcion . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . $unidad . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . $solicitada . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . $existencia . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . $comprar . '</td>'
                    . '</tr>';
            })
            ->implode('');

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead>'
            . '<tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Solicitada</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Existencia</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">A comprar</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>';
    }


    public static function configureForApprovals(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_solicitud_usuario')
                    ->label('N° solicitud')
                    ->state(fn ($record) => $record->numero_solicitud_usuario ?: $record->id)
                    ->sortable(),

                TextColumn::make('codigo_control')
                    ->label('N° control')
                    ->state(fn ($record) => $record->codigo_control ?: $record->id)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('para_ser_usado_en')
                    ->label('Para ser usado en')
                    ->searchable(),

                TextColumn::make('tipo_solicitud')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('prioridad')
                    ->label('Prioridad')
                    ->badge(),

                TextColumn::make('estado_general')
                    ->label('Estado')
                    ->state(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['label'])
                    ->badge()
                    ->visible(fn ($livewire): bool => ! self::isApprovalHistoryTab($livewire))
                    ->color(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['color']),

                TextColumn::make('estado_rol')
                    ->label('Estado')
                    ->state(fn (SolicitudCompra $record, $livewire): string => self::approvalRoleState($record, $livewire)['label'])
                    ->badge()
                    ->visible(fn ($livewire): bool => self::isApprovalHistoryTab($livewire))
                    ->color(fn (SolicitudCompra $record, $livewire): string => self::approvalRoleState($record, $livewire)['color']),

                TextColumn::make('contexto_historial')
                    ->label('Observación')
                    ->state(fn (SolicitudCompra $record): ?string => self::approvalReentryDescription($record))
                    ->toggleable()
                    ->wrap()
                    ->visible(fn ($livewire): bool => ! self::isApprovalHistoryTab($livewire)),
            ])
            ->recordActions([
                Action::make('imprimirPdf')
                    ->label('Imprimir / Guardar PDF')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('solicitudes-compra.formato.print', ['solicitudCompra' => $record]))
                    ->openUrlInNewTab(),

                Action::make('crearSumario')
                    ->label('Realizar Sumario')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('success')
                    ->visible(fn (SolicitudCompra $record, $livewire): bool => ! self::isApprovalHistoryTab($livewire)
                        && auth()->user()?->can('Create:Sumario')
                        && filled($record->fecha_receptor)
                        && (string) $record->estado !== 'RECHAZADA'
                        && self::hasPendingItemsForSumario($record))
                    ->url(fn (SolicitudCompra $record): string => SumarioResource::getUrl('create', [
                        'solicitud_compra_id' => $record->id,
                    ])),

                Action::make('trazabilidadSolicitud')
                    ->label('Trazabilidad')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('info')
                    ->visible(fn (SolicitudCompra $record): bool => filled($record->fecha_receptor))
                    ->modalHeading(fn (SolicitudCompra $record): string => 'Trazabilidad solicitud ' . (string) ($record->codigo_control ?: $record->id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->schema([
                        Placeholder::make('trazabilidad_detalle')
                            ->hiddenLabel()
                            ->content(fn (SolicitudCompra $record): HtmlString => new HtmlString(self::renderTrackingView($record)))
                            ->dehydrated(false),
                    ]),

                Action::make('verGestion')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (SolicitudCompra $record): string => 'Solicitud #' . ($record->numero_solicitud_usuario ?: $record->id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn (SolicitudCompra $record): array => self::getViewFormData($record))
                    ->schema(self::getViewSchema())
                    ->extraModalFooterActions(fn (SolicitudCompra $record): array => self::approvalModalFooterActions($record)),
            ])
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc');
    }

    private static function approvalModalFooterActions(SolicitudCompra $record): array
    {
        return [
            Action::make('firmarAlmacenDesdeModal')
                ->label('Firmar almacén')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('warning')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record->fresh()))
                ->schema(self::signatureSchema())
                ->action(function (array $data) use ($record): void {
                    self::signAlmacenFromModal($record, $data);
                }),

            Action::make('rechazarAlmacenDesdeModal')
                ->label('Rechazar almacén')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record->fresh()))
                ->schema(self::rejectionSchema())
                ->action(function (array $data) use ($record): void {
                    self::rejectFromModal($record, $data, 'almacen');
                }),

            Action::make('firmarAprobacionDesdeModal')
                ->label('Firmar aprobación')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignApprover(auth()->user(), $record->fresh()))
                ->schema(self::approverSignatureSchema())
                ->action(function (array $data) use ($record): void {
                    self::signApproverFromModal($record, $data);
                }),

            Action::make('rechazarAprobacionDesdeModal')
                ->label('Rechazar aprobación')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignApprover(auth()->user(), $record->fresh()))
                ->schema(self::rejectionSchema())
                ->action(function (array $data) use ($record): void {
                    self::rejectFromModal($record, $data, 'aprobador');
                }),

            Action::make('firmarProcuraDesdeModal')
                ->label('Firmar recepción procura')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('info')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignProcura(auth()->user(), $record->fresh()))
                ->schema(self::procuraSignatureSchema())
                ->action(function (array $data) use ($record): void {
                    self::signProcuraFromModal($record, $data);
                }),

            Action::make('rechazarProcuraDesdeModal')
                ->label('Rechazar procura')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignProcura(auth()->user(), $record->fresh()))
                ->schema(self::rejectionSchema())
                ->action(function (array $data) use ($record): void {
                    self::rejectFromModal($record, $data, 'procura');
                }),
        ];
    }

    private static function signatureSchema(): array
    {
        return [
            TextInput::make('password')
                ->label('Clave de firma')
                ->password()
                ->required(),
            TextInput::make('password_confirmation')
                ->label('Repetir clave de firma')
                ->password()
                ->required(),
        ];
    }

    private static function approverSignatureSchema(): array
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
            ...self::signatureSchema(),
        ];
    }

    private static function procuraSignatureSchema(): array
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
            ...self::signatureSchema(),
        ];
    }

    private static function rejectionSchema(): array
    {
        return [
            Textarea::make('comentario_rechazo')
                ->label('Comentario de rechazo')
                ->rows(4)
                ->required()
                ->maxLength(2000),
            ...self::signatureSchema(),
        ];
    }

    private static function signAlmacenFromModal(SolicitudCompra $record, array $data): void
    {
        $record = $record->fresh();

        if (! SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record) || ! self::validatePassword($data)) {
            return;
        }

        $record->forceFill([
            'por_almacen_user_id' => $record->por_almacen_user_id ?: auth()->id(),
            'cargo_almacen' => auth()->user()?->cargo?->nombre,
            'firma_almacen' => $record->firma_almacen,
            'fecha_almacen' => now()->toDateString(),
            'estado' => SolicitudCompra::ESTADO_EN_ESPERA_APROBADOR,
        ])->save();

        Notification::make()
            ->title('Firma de almacén registrada')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Firma de almacen registrada',
            'Se firmo en etapa almacen la solicitud #' . (string) $record->id . '.',
            'success'
        );
    }

    private static function signApproverFromModal(SolicitudCompra $record, array $data): void
    {
        $record = $record->fresh();

        if (! SolicitudCompraFlow::canSignApprover(auth()->user(), $record) || ! self::validatePassword($data)) {
            return;
        }

        $prioridad = (string) ($data['prioridad'] ?? '');
        if (! in_array($prioridad, ['Alta', 'Media', 'Baja'], true)) {
            Notification::make()
                ->title('Prioridad requerida')
                ->body('Debes seleccionar la prioridad antes de firmar la aprobacion.')
                ->danger()
                ->send();

            return;
        }

        $record->forceFill([
            'aprobado_por_user_id' => $record->aprobado_por_user_id ?: auth()->id(),
            'cargo_aprobador' => auth()->user()?->cargo?->nombre,
            'prioridad' => $prioridad,
            'firma_aprobador' => $record->firma_aprobador,
            'fecha_aprobador' => now()->toDateString(),
            'estado' => SolicitudCompra::ESTADO_EN_ESPERA_PROCURA,
        ])->save();

        Notification::make()
            ->title('Aprobación registrada')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Aprobacion registrada',
            'Se firmo en etapa aprobacion la solicitud #' . (string) $record->id . '.',
            'success'
        );
    }

    private static function signProcuraFromModal(SolicitudCompra $record, array $data): void
    {
        $record = $record->fresh();

        if (! SolicitudCompraFlow::canSignProcura(auth()->user(), $record) || ! self::validatePassword($data)) {
            return;
        }

        $record->forceFill([
            'recibido_por_user_id' => $record->recibido_por_user_id ?: auth()->id(),
            'cargo_receptor' => auth()->user()?->cargo?->nombre,
            'codigo_control_procura' => $record->codigo_control_procura ?: ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
            'firma_receptor' => $record->firma_receptor,
            'fecha_receptor' => now()->toDateString(),
            'hora_receptor' => now()->format('H:i:s'),
            'estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA,
        ])->save();

        Notification::make()
            ->title('Recepción de procura registrada')
            ->success()
            ->send();

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

    private static function rejectFromModal(SolicitudCompra $record, array $data, string $etapa): void
    {
        $record = $record->fresh();

        $canReject = match ($etapa) {
            'almacen' => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record),
            'aprobador' => SolicitudCompraFlow::canSignApprover(auth()->user(), $record),
            'procura' => SolicitudCompraFlow::canSignProcura(auth()->user(), $record),
            default => false,
        };

        if (! $canReject || ! self::validatePassword($data)) {
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

    private static function validatePassword(array $data): bool
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

    private static function ensureDraftSumarioForSolicitud(SolicitudCompra $record): void
    {
        $existingDraft = Sumario::query()
            ->where('solicitud_compra_id', $record->id)
            ->where('workflow_estado', 'BORRADOR')
            ->exists();

        if ($existingDraft) {
            return;
        }

        $tipoOrden = str_contains(strtoupper((string) ($record->tipo_solicitud ?? '')), 'SERVICIO')
            ? 'SERVICIO'
            : 'COMPRA';

        Sumario::query()->create([
            'solicitud_compra_id' => $record->id,
            'correlativo_sdc' => ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc'),
            'fecha' => now()->toDateString(),
            'procedencia' => 'LOCAL',
            'tipo_orden' => $tipoOrden,
            'departamento_solicitante' => (string) ($record->departamento_solicitante ?: 'PENDIENTE'),
            'estado' => 'BORRADOR',
            'workflow_estado' => 'BORRADOR',
            'elaborado_por_user_id' => auth()->id(),
        ]);
    }

    private static function isApprovalHistoryTab(mixed $livewire = null): bool
    {
        $activeTab = self::resolveActiveApprovalTab($livewire);

        return str_starts_with($activeTab, 'historial_');
    }

    private static function currentApprovalRoleKey(mixed $livewire = null): ?string
    {
        $activeTab = self::resolveActiveApprovalTab($livewire);

        return match ($activeTab) {
            'historial_almacen' => 'almacen',
            'historial_aprobacion' => 'aprobador',
            'historial_procura' => 'procura',
            default => null,
        };
    }

    private static function approvalRoleState(SolicitudCompra $record, mixed $livewire = null): array
    {
        $roleKey = self::currentApprovalRoleKey($livewire);

        if ($roleKey === null) {
            return ['label' => '-', 'color' => 'gray'];
        }

        return SolicitudCompraFlow::roleHistoryState($record, $roleKey);
    }

    private static function approvalReentryDescription(SolicitudCompra $record): ?string
    {
        $previousRejectedVersion = SolicitudCompraFlow::previousRejectedVersion($record);

        if (! $previousRejectedVersion) {
            return null;
        }

        $rejectionStage = SolicitudCompraFlow::rejectionStageLabel($previousRejectedVersion->rechazo_etapa);

        if ($rejectionStage === null) {
            return null;
        }

        $pendingRoleKey = self::pendingApprovalRoleKey($record);

        // Solo mostramos observacion al rol anterior directo de la etapa que rechazo.
        // Ejemplos:
        // - Rechazo en APROBADOR => lo ve ALMACEN
        // - Rechazo en PROCURA => lo ve APROBADOR
        // - Rechazo en ALMACEN => no aplica en modulo de aprobaciones
        if (! self::shouldShowReentryObservation($pendingRoleKey, $previousRejectedVersion->rechazo_etapa)) {
            return null;
        }

        return 'Procesada previamente, rechazada por ' . strtolower($rejectionStage) . '.';
    }

    private static function shouldShowReentryObservation(?string $pendingRoleKey, ?string $rejectionStage): bool
    {
        return match ((string) $rejectionStage) {
            'aprobador' => $pendingRoleKey === 'almacen',
            'procura' => $pendingRoleKey === 'aprobador',
            default => false,
        };
    }

    private static function pendingApprovalRoleKey(SolicitudCompra $record): ?string
    {
        if (filled($record->firma_solicitante) && blank($record->fecha_almacen)) {
            return 'almacen';
        }

        if (filled($record->fecha_almacen) && blank($record->fecha_aprobador)) {
            return 'aprobador';
        }

        if (filled($record->fecha_aprobador) && blank($record->fecha_receptor)) {
            return 'procura';
        }

        return null;
    }

    private static function hasPendingItemsForSumario(SolicitudCompra $record): bool
    {
        return $record->items()
            ->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)')
            ->exists();
    }

    private static function resolveActiveApprovalTab(mixed $livewire = null): string
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

    /**
     * @return array{label:string,color:string}
     */
    private static function resolveGeneralState(SolicitudCompra $record): array
    {
        $recordId = (int) $record->id;

        if (isset(self::$generalStateCache[$recordId])) {
            return self::$generalStateCache[$recordId];
        }

        if ((string) $record->estado === 'RECHAZADA') {
            return self::$generalStateCache[$recordId] = ['label' => 'Rechazada', 'color' => 'danger'];
        }

        if ((string) $record->estado === SolicitudCompra::ESTADO_COMPLETADA) {
            return self::$generalStateCache[$recordId] = ['label' => 'Completada', 'color' => 'success'];
        }

        if ((string) $record->estado === 'BORRADOR' || blank($record->firma_solicitante)) {
            return self::$generalStateCache[$recordId] = ['label' => 'Borrador', 'color' => 'gray'];
        }

        if (blank($record->fecha_almacen)) {
            return self::$generalStateCache[$recordId] = ['label' => 'En espera de almacen', 'color' => 'warning'];
        }

        if (blank($record->fecha_aprobador)) {
            return self::$generalStateCache[$recordId] = ['label' => 'En espera de aprobador', 'color' => 'warning'];
        }

        if (blank($record->fecha_receptor)) {
            return self::$generalStateCache[$recordId] = ['label' => 'En espera de procura', 'color' => 'warning'];
        }

        $sumarioIds = $record->sumarios()->pluck('id');

        if ($sumarioIds->isEmpty()) {
            return self::$generalStateCache[$recordId] = ['label' => 'Recibido por procura', 'color' => 'info'];
        }

        $ordenes = OrdenCompra::query()
            ->whereIn('sumario_id', $sumarioIds)
            ->get(['estado', 'workflow_post_compra']);

        if ($ordenes->isEmpty()) {
            return self::$generalStateCache[$recordId] = ['label' => 'En sumario de cotizaciones', 'color' => 'info'];
        }

        $workflows = $ordenes->pluck('workflow_post_compra')->filter()->map(fn ($value): string => (string) $value);
        $estados = $ordenes->pluck('estado')->filter()->map(fn ($value): string => (string) $value);

        if ($workflows->contains('RECHAZO_SOLICITANTE') || $workflows->contains('RECHAZADA_SOLICITANTE')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Con rechazo del solicitante', 'color' => 'danger'];
        }

        if ($workflows->contains('FACTURA_PROCESADA_ADMINISTRACION') || $workflows->contains('BACKUP_FACTURA_COMPLETADO')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Factura procesada por administracion', 'color' => 'success'];
        }

        if ($workflows->contains('FACTURA_ENVIADA_ADMINISTRACION')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Factura enviada a administracion', 'color' => 'info'];
        }

        if ($workflows->contains('EN_TRANSICION_ALMACEN')) {
            return self::$generalStateCache[$recordId] = ['label' => 'En transicion a almacen', 'color' => 'info'];
        }

        if ($workflows->contains('EN_ESPERA_DE_PRODUCTO') || $workflows->contains('ESPERANDO_PRODUCTO')) {
            return self::$generalStateCache[$recordId] = ['label' => 'En espera de producto', 'color' => 'warning'];
        }

        if ($workflows->contains('PAGO_CONFIRMADO_PROCURA')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Pago confirmado por procura', 'color' => 'info'];
        }

        if ($workflows->contains('PAGO_REGISTRADO_FINANZAS')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Pago registrado por finanzas', 'color' => 'info'];
        }

        if ($workflows->contains('PENDIENTE_PAGO_FINANZAS')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Pendiente de pago finanzas', 'color' => 'warning'];
        }

        if ($estados->contains('PENDIENTE_APROBACION')) {
            return self::$generalStateCache[$recordId] = ['label' => 'ODC pendiente de aprobacion', 'color' => 'warning'];
        }

        return self::$generalStateCache[$recordId] = ['label' => 'En proceso de ODC', 'color' => 'gray'];
    }

    private static function renderTrackingView(SolicitudCompra $record): string
    {
        $tracking = self::buildTrackingData($record);
        $summary = $tracking['summary'];
        $items = $tracking['items'];
        $internalCycle = self::resolveInternalRequestCycle($record);

        $rows = collect($items)
            ->map(function (array $item): string {
                return '<tr>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['item']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $item['descripcion']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['estado_item']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['sumarios']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['odcs']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $item['fase']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;font-weight:700;">' . e((string) $item['porcentaje']) . '%</td>'
                    . '</tr>';
            })
            ->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="7" style="border:1px solid #d1d5db;padding:10px;text-align:center;color:#6b7280;">Sin items registrados.</td></tr>';
        }

        return '<div style="display:grid;gap:12px;">'
            . '<div style="border:1px solid #d1d5db;border-radius:10px;padding:12px;background:#f9fafb;">'
            . '<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.03em;">Ciclo interno de la solicitud (antes de procura)</div>'
            . '<div style="font-size:14px;font-weight:700;color:#111827;margin-top:4px;">Estado actual: ' . e($internalCycle['current_label']) . '</div>'
            . '<div style="font-size:12px;color:#374151;margin-top:6px;">NACE LA SOLICITUD = EN ESPERA DE ALMACEN / ALMACEN APRUEBA = EN ESPERA DE APROBADOR / APROBADOR APRUEBA = EN ESPERA DE PROCURA / PROCURA FIRMA = RECIBIDO POR PROCURA</div>'
            . '</div>'
            . '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">'
            . self::trackingCard('Sumarios', (string) $summary['sumarios_count'])
            . self::trackingCard('ODC', (string) $summary['odcs_count'])
            . self::trackingCard('Items', (string) $summary['items_count'])
            . self::trackingCard('Avance general', (string) $summary['progress'] . '%')
            . '</div>'
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Sumarios</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">ODC</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Fase actual</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Avance</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>';
    }

    private static function trackingCard(string $title, string $value): string
    {
        return '<div style="border:1px solid #d1d5db;border-radius:10px;padding:10px;background:#f9fafb;">'
            . '<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.03em;">' . e($title) . '</div>'
            . '<div style="font-size:20px;font-weight:700;color:#111827;">' . e($value) . '</div>'
            . '</div>';
    }

    /**
     * @return array{summary: array{sumarios_count:int,odcs_count:int,items_count:int,progress:int}, items: array<int, array<string, mixed>>}
     */
    private static function buildTrackingData(SolicitudCompra $record): array
    {
        $solicitudId = (int) $record->id;

        $items = SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->withCount('sumarioItems')
            ->orderBy('item')
            ->get();

        $itemIds = $items->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        $ocItems = OrdenCompraItem::query()
            ->whereIn('solicitud_compra_item_id', $itemIds)
            ->whereHas('ordenCompra.sumario', fn ($query) => $query->where('solicitud_compra_id', $solicitudId))
            ->with('ordenCompra:id,correlativo_odc,workflow_post_compra,estado')
            ->get()
            ->groupBy('solicitud_compra_item_id');

        $rows = [];
        $progressAccumulator = 0;

        foreach ($items as $item) {
            $itemOcRows = $ocItems->get($item->id, collect());

            $status = self::resolveItemTrackingStatus(
                $record,
                (string) ($item->estado_item ?: 'SIN_PROCESAR'),
                $itemOcRows->sortByDesc('id')->first()
            );

            $odcCount = $itemOcRows
                ->pluck('orden_compra_id')
                ->unique()
                ->count();

            $progressAccumulator += $status['percent'];

            $rows[] = [
                'item' => $item->item ?: $item->id,
                'descripcion' => $item->descripcion,
                'estado_item' => str_replace('_', ' ', (string) ($item->estado_item ?: 'SIN_PROCESAR')),
                'sumarios' => (int) $item->sumario_items_count,
                'odcs' => $odcCount,
                'fase' => $status['label'],
                'porcentaje' => $status['percent'],
            ];
        }

        $itemsCount = count($rows);
        $progress = $itemsCount > 0 ? (int) round($progressAccumulator / $itemsCount) : 0;

        return [
            'summary' => [
                'sumarios_count' => $record->sumarios()->count(),
                'odcs_count' => $record->sumarios()->withCount('ordenesCompra')->get()->sum('ordenes_compra_count'),
                'items_count' => $itemsCount,
                'progress' => $progress,
            ],
            'items' => $rows,
        ];
    }

    /**
     * @return array{label:string,percent:int}
     */
    private static function resolveItemTrackingStatus(SolicitudCompra $record, string $estadoItem, mixed $latestOcItem): array
    {
        $internalCycle = self::resolveInternalRequestCycle($record);

        if (! $latestOcItem) {
            return match ($estadoItem) {
                'EN_SUMARIO' => ['label' => 'Comparativo en sumario', 'percent' => 40],
                'EN_OC' => ['label' => 'En ODC (sin detalle)', 'percent' => 60],
                default => ['label' => $internalCycle['current_label'], 'percent' => $internalCycle['percent']],
            };
        }

        $workflow = (string) ($latestOcItem->ordenCompra->workflow_post_compra ?? '');
        $recepcionEstado = (string) ($latestOcItem->estado_recepcion ?? '');

        if ($estadoItem === 'CERRADO') {
            return ['label' => 'Entregado al solicitante', 'percent' => 100];
        }

        if (filled($latestOcItem->procesado_almacen_at)) {
            return ['label' => 'Entrada oficial parcial', 'percent' => 95];
        }

        return match ($workflow) {
            'PENDIENTE_PAGO_FINANZAS' => ['label' => 'ODC pendiente de pago', 'percent' => 60],
            'PAGO_REGISTRADO_FINANZAS' => ['label' => 'Pago registrado por Finanzas', 'percent' => 70],
            'PAGO_CONFIRMADO_PROCURA' => ['label' => 'Pago confirmado por Procura', 'percent' => 75],
            'ESPERANDO_PRODUCTO' => ['label' => 'Esperando producto', 'percent' => 80],
            'EN_ESPERA_DE_PRODUCTO' => ['label' => 'Esperando producto', 'percent' => 80],
            'EN_TRANSICION_ALMACEN' => ['label' => 'En transicion a almacen', 'percent' => 85],
            'FACTURA_ENVIADA_ADMINISTRACION' => ['label' => 'Factura enviada a Administracion', 'percent' => 90],
            'FACTURA_PROCESADA_ADMINISTRACION' => ['label' => 'Factura procesada por Administracion', 'percent' => 95],
            'BACKUP_FACTURA_COMPLETADO' => ['label' => 'Factura procesada (respaldo completo)', 'percent' => 95],
            'CERRADA_CONFORME' => ['label' => 'Cerrada conforme', 'percent' => 100],
            'RECHAZO_SOLICITANTE' => ['label' => 'Rechazo del solicitante', 'percent' => 65],
            default => ['label' => 'ODC en proceso', 'percent' => 60],
        };
    }

    /**
     * @return array{current_label:string,percent:int}
     */
    private static function resolveInternalRequestCycle(SolicitudCompra $record): array
    {
        if ((string) $record->estado === 'BORRADOR' || blank($record->firma_solicitante)) {
            return [
                'current_label' => 'Borrador (sin enviar)',
                'percent' => 0,
            ];
        }

        if (blank($record->fecha_almacen)) {
            return [
                'current_label' => 'En espera de almacen',
                'percent' => 10,
            ];
        }

        if (blank($record->fecha_aprobador)) {
            return [
                'current_label' => 'En espera de aprobador',
                'percent' => 20,
            ];
        }

        if (blank($record->fecha_receptor)) {
            return [
                'current_label' => 'En espera de procura',
                'percent' => 30,
            ];
        }

        return [
            'current_label' => 'Recibido por procura',
            'percent' => 40,
        ];
    }
}
