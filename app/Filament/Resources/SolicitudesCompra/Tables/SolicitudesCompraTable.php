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
use App\Support\OrdenCompraConformidadService;
use App\Support\SolicitudCompraFlow;
use App\Support\UserSignaturePath;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
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
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('numero_solicitud_usuario')
                    ->toggleable()
                    ->label('N° Solicitud')
                    ->state(fn ($record) => $record->numero_solicitud_usuario ?: $record->id)
                    ->sortable(),

                TextColumn::make('codigo_control')
                    ->toggleable()
                    ->label('N° Control Solicitud')
                    ->state(fn ($record) => $record->codigo_control ?: $record->id)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->toggleable()
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('para_ser_usado_en')
                    ->toggleable()
                    ->label('Para ser usado en')
                    ->grow()
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->wrap()
                    ->extraAttributes([
                        'style' => 'white-space: normal; word-break: break-word; overflow-wrap: anywhere; line-height: 1.4;',
                    ])
                    ->searchable(),

                TextColumn::make('tipo_solicitud')
                    ->toggleable()
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('prioridad')
                    ->toggleable()
                    ->label('Prioridad')
                    ->badge(),

                TextColumn::make('estado_general')
                    ->toggleable()
                    ->label('Estado')
                    ->state(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['label'])
                    ->badge()
                    ->visible(fn ($livewire): bool => ! self::isApprovalHistoryTab($livewire) && ! self::isRequesterHistoryTab($livewire))
                    ->color(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['color']),

                TextColumn::make('estado_historial_solicitante')
                    ->toggleable()    
                    ->label('Estado')
                    ->state(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['label'])
                    ->badge()
                    ->visible(fn ($livewire): bool => self::isRequesterHistoryTab($livewire))
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
                    ->modalHeading(fn (SolicitudCompra $record): string => 'Conformidad de Materiales | Solicitud ' . (string) ($record->codigo_control ?: $record->id))
                    ->modalDescription(function (SolicitudCompra $record): string {
                        $ordenCompra = self::pendingConformidadOrdenCompra($record);

                        if (! $ordenCompra) {
                            return 'No se encontro una ODC en transicion con items pendientes.';
                        }

                        return 'Productos llegados en la ODC ' . (string) ($ordenCompra->correlativo_odc ?: ('#' . $ordenCompra->id))
                            . '. Marca cada item como Aceptar o Rechazar a Devoluciones.';
                    })
                    ->fillForm(fn (SolicitudCompra $record): array => [
                        'orden_compra_id' => self::firstPendingConformidadOrdenCompraId($record),
                        'items_conformidad' => self::buildConformidadRowsForSolicitante($record),
                    ])
                    ->form([
                        Hidden::make('orden_compra_id')
                            ->required(),
                        Repeater::make('items_conformidad')
                            ->label('Productos llegados (OC generada)')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('orden_compra_item_id')->required(),
                                Hidden::make('cantidad_llegada_raw'),
                                TextInput::make('item')
                                    ->label('Item')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('descripcion')
                                    ->label('Descripcion')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('cantidad_llegada')
                                    ->label('Cantidad llegada')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('faltante_solicitud')
                                    ->label('Faltante en solicitud')
                                    ->disabled()
                                    ->dehydrated(false),
                                Radio::make('decision')
                                    ->label('Decision')
                                    ->options([
                                        'ACEPTADO' => 'Aceptar',
                                        'RECHAZADO' => 'Rechazar a Devoluciones',
                                    ])
                                    ->required()
                                    ->live(),
                                TextInput::make('cantidad_rechazada')
                                    ->label('Cantidad rechazada')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->helperText('El resto de la cantidad llegada se marcara como entregado automaticamente.')
                                    ->required(fn (callable $get): bool => (string) ($get('decision') ?? '') === 'RECHAZADO')
                                    ->visible(fn (callable $get): bool => (string) ($get('decision') ?? '') === 'RECHAZADO'),
                                Textarea::make('motivo')
                                    ->label('Motivo (si rechaza)')
                                    ->rows(2)
                                    ->required(fn (callable $get): bool => (string) ($get('decision') ?? '') === 'RECHAZADO')
                                    ->visible(fn (callable $get): bool => (string) ($get('decision') ?? '') === 'RECHAZADO'),
                            ])
                            ->columns(2),
                    ])
                    ->action(function (array $data, SolicitudCompra $record): void {
                        try {
                            $ordenCompraId = (int) ($data['orden_compra_id'] ?? 0);

                            $ordenCompra = OrdenCompra::query()
                                ->with(['sumario.solicitudCompra'])
                                ->find($ordenCompraId);

                            if (! $ordenCompra || (int) ($ordenCompra->sumario?->solicitudCompra?->id ?? 0) !== (int) $record->id) {
                                throw new \RuntimeException('No se encontro una ODC valida para registrar conformidad en esta solicitud.');
                            }

                            app(OrdenCompraConformidadService::class)->registrarConformidadPorItems(
                                $ordenCompra,
                                auth()->user(),
                                $data['items_conformidad'] ?? []
                            );

                            $hasRejected = collect($data['items_conformidad'] ?? [])
                                ->contains(fn (array $row): bool => strtoupper((string) ($row['decision'] ?? '')) === 'RECHAZADO');

                            Notification::make()
                                ->title('Conformidad registrada')
                                ->body($hasRejected
                                    ? 'Se registraron rechazos y se envio a flujo de devoluciones para los items marcados.'
                                    : 'Se registraron los productos llegados correctamente.')
                                ->success()
                                ->send();

                            ActivityNotification::record(
                                auth()->user(),
                                'Conformidad de materiales desde Solicitudes',
                                'Se registro conformidad para la solicitud ' . (string) ($record->codigo_control ?: $record->id) . '.',
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

                EditAction::make()
                    ->authorize(fn ($record): bool => SolicitudCompraFlow::canEditRequest(auth()->user(), $record))
                    ->visible(fn ($record): bool => SolicitudCompraFlow::canEditRequest(auth()->user(), $record)),
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
            ->whereIn('workflow_post_compra', ['EN_TRANSICION_ALMACEN', 'DEVOLUCION_REALIZADA'])
            ->whereHas('items', fn ($query) => $query->whereNull('decision_solicitante'))
            ->orderByDesc('id')
            ->value('id');

        return $ordenCompraId ? (int) $ordenCompraId : null;
    }

    private static function pendingConformidadOrdenCompra(SolicitudCompra $record): ?OrdenCompra
    {
        $ordenCompraId = self::firstPendingConformidadOrdenCompraId($record);

        if (! $ordenCompraId) {
            return null;
        }

        return OrdenCompra::query()
            ->with(['items.solicitudCompraItem.ordenCompraItems', 'sumario.solicitudCompra'])
            ->find($ordenCompraId);
    }

    private static function buildConformidadRowsForSolicitante(SolicitudCompra $record): array
    {
        $ordenCompra = self::pendingConformidadOrdenCompra($record);

        if (! $ordenCompra) {
            return [];
        }

        return $ordenCompra->items()
            ->whereNull('decision_solicitante')
            ->orderBy('id')
            ->get()
            ->map(function (OrdenCompraItem $item): array {
                $solicitudItem = $item->solicitudCompraItem;

                $cantidadObjetivo = round((float) (
                    $solicitudItem?->cantidad_pedida
                    ?? $solicitudItem?->cantidad_a_comprar
                    ?? $solicitudItem?->cantidad_solicitada
                    ?? $item->cantidad
                    ?? 0
                ), 2);

                $cantidadAceptadaHistorica = round((float) ($solicitudItem?->ordenCompraItems()
                    ->where('decision_solicitante', 'ACEPTADO')
                    ->sum('cantidad') ?? 0), 2);

                $cantidadLlegada = round((float) ($item->cantidad ?? 0), 2);
                $faltanteActual = max(0, round($cantidadObjetivo - $cantidadAceptadaHistorica, 2));

                return [
                    'orden_compra_item_id' => (int) $item->id,
                    'item' => (string) ($item->item ?? ('#' . $item->id)),
                    'descripcion' => (string) ($item->descripcion ?? ''),
                    'cantidad_llegada_raw' => $cantidadLlegada,
                    'cantidad_llegada' => number_format($cantidadLlegada, 2, ',', '.'),
                    'faltante_solicitud' => number_format((float) $faltanteActual, 2, ',', '.'),
                    'decision' => null,
                    'cantidad_rechazada' => null,
                    'motivo' => '',
                ];
            })
            ->all();
    }

    private static function getViewSchema(): array
    {
        return [
            Section::make('Resumen de solicitud')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            TextInput::make('codigo_control')
                                ->label('N° Control Solicitud')
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

            Section::make('Historial de rechazos')
                ->description('Muestra los rechazos previos registrados para esta solicitud.')
                ->visible(fn (SolicitudCompra $record): bool => self::hasRejectionHistory($record))
                ->schema([
                    Placeholder::make('historial_rechazos_html')
                        ->hiddenLabel()
                        ->content(fn (SolicitudCompra $record): HtmlString => new HtmlString(self::renderRejectionHistoryView($record)))
                        ->dehydrated(false),
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
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . $solicitada . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . $existencia . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . $comprar . '</td>'
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

    private static function renderRejectionHistoryView(SolicitudCompra $record): string
    {
        $sharedCode = (string) ($record->codigo_control ?: $record->id);

        $rows = SolicitudCompra::query()
            ->with('rechazoPor')
            ->where('codigo_control', $sharedCode)
            ->whereNotNull('rechazo_comentario')
            ->orderBy('id')
            ->get()
            ->map(function (SolicitudCompra $version, int $index): string {
                $stage = SolicitudCompraFlow::rejectionStageLabel($version->rechazo_etapa) ?: 'SIN ETAPA';
                $rejectedBy = e((string) ($version->rechazoPor?->name ?: 'Sin usuario'));
                $rejectedAt = e((string) ($version->rechazo_en?->format('d/m/Y H:i') ?: '-'));
                $comment = nl2br(e((string) ($version->rechazo_comentario ?: 'Sin comentario registrado.')));
                $attempt = $index + 1;

                return '<div style="border:1px solid #d1d5db;border-radius:8px;padding:12px;margin-bottom:12px;background:#fff;">'
                    . '<div style="font-weight:600;color:#111827;margin-bottom:8px;">Rechazo ' . $attempt . '</div>'
                    . '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 12px;font-size:12px;margin-bottom:8px;">'
                    . '<div><strong>Etapa:</strong><br>' . e($stage) . '</div>'
                    . '<div><strong>Rechazada por:</strong><br>' . $rejectedBy . '</div>'
                    . '<div><strong>Fecha:</strong><br>' . $rejectedAt . '</div>'
                    . '</div>'
                    . '<div style="font-size:12px;line-height:1.5;"><strong>Comentario:</strong><br>' . $comment . '</div>'
                    . '</div>';
            })
            ->implode('');

        if ($rows === '') {
            return '';
        }

        return '<div>' . $rows . '</div>';
    }

    private static function hasRejectionHistory(SolicitudCompra $record): bool
    {
        if ((string) $record->estado !== SolicitudCompra::ESTADO_RECHAZADA) {
            return false;
        }

        $sharedCode = (string) ($record->codigo_control ?: $record->id);

        return SolicitudCompra::query()
            ->where('codigo_control', $sharedCode)
            ->whereNotNull('rechazo_comentario')
            ->exists();
    }


    public static function configureForApprovals(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->modifyQueryUsing(fn ($query) => $query->with('solicitadoPor'))
            ->columns([
                TextColumn::make('codigo_control')
                    ->toggleable()
                    ->label('N° Control Solicitud')
                    ->state(fn ($record) => $record->codigo_control ?: $record->id)
                    ->sortable(),

                TextColumn::make('solicitadoPor.name')
                    ->toggleable()
                    ->label('Solicitante')
                    ->state(fn ($record): string => (string) ($record->solicitadoPor?->name ?: '-'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->toggleable()
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('para_ser_usado_en')
                    ->toggleable()
                    ->label('Para ser usado en')
                    ->grow()
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->wrap()
                    ->extraAttributes([
                        'style' => 'white-space: normal; word-break: break-word; overflow-wrap: anywhere; line-height: 1.4;',
                    ])
                    ->searchable(),

                TextColumn::make('tipo_solicitud')
                    ->toggleable()
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('prioridad')
                    ->toggleable()
                    ->label('Prioridad')
                    ->badge(),

                TextColumn::make('estado_general')
                    ->toggleable()
                    ->label('Estado')
                    ->state(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['label'])
                    ->badge()
                    ->visible(fn ($livewire): bool => ! self::isApprovalHistoryTab($livewire))
                    ->color(fn (SolicitudCompra $record): string => self::resolveGeneralState($record)['color']),

                TextColumn::make('estado_rol')
                    ->toggleable()
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

                Action::make('verArchivosHistorialProcura')
                    ->label('Ver Archivos')
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->color('primary')
                    ->visible(fn (SolicitudCompra $record, $livewire): bool => self::isProcuraHistoryTab($livewire)
                        && filled($record->fecha_receptor))
                    ->modalHeading(fn (SolicitudCompra $record): string => 'Archivos de la solicitud ' . (string) ($record->codigo_control ?: $record->id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->schema([
                        Placeholder::make('archivos_historial_procura')
                            ->hiddenLabel()
                            ->content(fn (SolicitudCompra $record): HtmlString => new HtmlString(self::renderFilesHubView($record)))
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
                ->successRedirectUrl(AprobacionesCompraResource::getUrl('index'))
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record->fresh()))
                ->schema(self::signatureSchema())
                ->action(function (array $data) use ($record): void {
                    self::signAlmacenFromModal($record, $data);
                }),

            Action::make('rechazarAlmacenDesdeModal')
                ->label('Rechazar almacén')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->successRedirectUrl(AprobacionesCompraResource::getUrl('index'))
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record->fresh()))
                ->schema(self::rejectionSchema())
                ->action(function (array $data) use ($record): void {
                    self::rejectFromModal($record, $data, 'almacen');
                }),

            Action::make('firmarAprobacionDesdeModal')
                ->label('Firmar aprobación')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->successRedirectUrl(AprobacionesCompraResource::getUrl('index'))
                ->visible(fn (): bool => SolicitudCompraFlow::canSignApprover(auth()->user(), $record->fresh()))
                ->schema(self::approverSignatureSchema())
                ->action(function (array $data) use ($record): void {
                    self::signApproverFromModal($record, $data);
                }),

            Action::make('rechazarAprobacionDesdeModal')
                ->label('Rechazar aprobación')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->successRedirectUrl(AprobacionesCompraResource::getUrl('index'))
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
                ->action(function (array $data, $livewire) use ($record): void {
                    $redirectUrl = self::signProcuraFromModal($record, $data);

                    if (filled($redirectUrl)) {
                        $livewire->redirect($redirectUrl, navigate: true);
                    }
                }),

            Action::make('rechazarProcuraDesdeModal')
                ->label('Rechazar procura')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->successRedirectUrl(AprobacionesCompraResource::getUrl('index'))
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
                    'SI' => 'Sí, abrir pestaña Creación de Sumarios',
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
            'firma_almacen' => UserSignaturePath::resolveForUser(auth()->user(), '__ENVIADA__'),
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
            'firma_aprobador' => UserSignaturePath::resolveForUser(auth()->user(), '__ENVIADA__'),
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

    private static function signProcuraFromModal(SolicitudCompra $record, array $data): ?string
    {
        $record = $record->fresh();

        if (! SolicitudCompraFlow::canSignProcura(auth()->user(), $record) || ! self::validatePassword($data)) {
            return null;
        }

        $record->forceFill([
            'recibido_por_user_id' => $record->recibido_por_user_id ?: auth()->id(),
            'cargo_receptor' => auth()->user()?->cargo?->nombre,
            'codigo_control_procura' => $record->codigo_control_procura ?: ControlCodeGenerator::generate('PROC', SolicitudCompra::class, 'codigo_control_procura'),
            'firma_receptor' => UserSignaturePath::resolveForUser(auth()->user(), '__ENVIADA__'),
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

        if ((string) ($data['crear_sumario_ahora'] ?? 'NO') === 'SI') {
            if (self::hasPendingItemsForSumario($record)) {
                return SumarioResource::getUrl('create', [
                    'solicitud_compra_id' => $record->id,
                ]);
            }

            Notification::make()
                ->title('Sin items pendientes para sumario')
                ->body('Todos los items ya fueron llevados a sumario para esta solicitud.')
                ->warning()
                ->send();
        }

        return null;
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

            $notification = Notification::make()
                ->title('Solicitud rechazada en etapa ' . strtoupper($etapa))
                ->body('Solicitud #' . $record->id . ' rechazada por ' . $rechazadoPor . '. Motivo: ' . $comentario)
                ->danger();

            \App\Support\Filament\DatabaseNotificationSender::sendNow($notification, $destinatario, dispatchEvent: true);
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

    private static function ensureDraftSumarioForSolicitud(SolicitudCompra $record): Sumario
    {
        $existingDraft = Sumario::query()
            ->where('solicitud_compra_id', $record->id)
            ->where('workflow_estado', 'BORRADOR')
            ->latest('id')
            ->first();

        if ($existingDraft) {
            return $existingDraft;
        }

        $tipoOrden = str_contains(strtoupper((string) ($record->tipo_solicitud ?? '')), 'SERVICIO')
            ? 'SERVICIO'
            : 'COMPRA';

        return Sumario::query()->create([
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

    private static function isRequesterHistoryTab(mixed $livewire = null): bool
    {
        return self::resolveActiveApprovalTab($livewire) === 'historial_solicitudes';
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

    private static function isProcuraHistoryTab(mixed $livewire = null): bool
    {
        return self::resolveActiveApprovalTab($livewire) === 'historial_procura';
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

        $conformidadSnapshot = self::resolveGeneralConformidadSnapshot($record, $sumarioIds->all());

        if ($conformidadSnapshot['all_completed']) {
            return self::$generalStateCache[$recordId] = ['label' => 'Completada', 'color' => 'success'];
        }

        if ($conformidadSnapshot['accepted_quantity'] > 0) {
            return self::$generalStateCache[$recordId] = ['label' => 'Completada parcialmente', 'color' => 'warning'];
        }

        if ($conformidadSnapshot['has_any_decision'] && $conformidadSnapshot['rejected_quantity'] > 0) {
            return self::$generalStateCache[$recordId] = ['label' => 'Pendiente de devolucion', 'color' => 'danger'];
        }

        $ordenes = OrdenCompra::query()
            ->whereIn('sumario_id', $sumarioIds)
            ->get(['estado', 'workflow_post_compra']);

        if ($ordenes->isEmpty()) {
            return self::$generalStateCache[$recordId] = ['label' => 'En proceso administrativo', 'color' => 'info'];
        }

        $workflows = $ordenes->pluck('workflow_post_compra')->filter()->map(fn ($value): string => (string) $value);
        $estados = $ordenes->pluck('estado')->filter()->map(fn ($value): string => (string) $value);

        if ($workflows->contains('EN_TRANSICION_ALMACEN')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Disponible en zona de transicion', 'color' => 'info'];
        }

        if ($workflows->contains('EN_ESPERA_DE_PRODUCTO') || $workflows->contains('ESPERANDO_PRODUCTO')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Productos en camino', 'color' => 'warning'];
        }

        if ($workflows->contains('PAGO_CONFIRMADO_PROCURA') || $workflows->contains('PAGO_REGISTRADO_FINANZAS')) {
            return self::$generalStateCache[$recordId] = ['label' => 'Pago registrado', 'color' => 'info'];
        }

        if ($workflows->contains('PENDIENTE_PAGO_FINANZAS')
            || $estados->contains('PENDIENTE_APROBACION')
            || $workflows->contains('FACTURA_ENVIADA_ADMINISTRACION')
            || $workflows->contains('FACTURA_PROCESADA_ADMINISTRACION')
            || $workflows->contains('BACKUP_FACTURA_COMPLETADO')
            || $workflows->contains('RECHAZO_SOLICITANTE')
            || $workflows->contains('RECHAZADA_SOLICITANTE')) {
            return self::$generalStateCache[$recordId] = ['label' => 'En proceso administrativo', 'color' => 'info'];
        }

        return self::$generalStateCache[$recordId] = ['label' => 'En proceso administrativo', 'color' => 'info'];
    }

    /**
     * @param  array<int, int|string>  $sumarioIds
     * @return array{accepted_quantity:float,rejected_quantity:float,has_any_decision:bool,all_completed:bool}
     */
    private static function resolveGeneralConformidadSnapshot(SolicitudCompra $record, array $sumarioIds): array
    {
        $items = SolicitudCompraItem::query()
            ->where('solicitud_compra_id', (int) $record->id)
            ->get(['id', 'cantidad_pedida', 'cantidad_a_comprar', 'cantidad_solicitada']);

        if ($items->isEmpty() || $sumarioIds === []) {
            return [
                'accepted_quantity' => 0.0,
                'rejected_quantity' => 0.0,
                'has_any_decision' => false,
                'all_completed' => false,
            ];
        }

        $itemIds = $items->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $ocItems = OrdenCompraItem::query()
            ->whereIn('solicitud_compra_item_id', $itemIds)
            ->whereHas('ordenCompra', fn ($query) => $query->whereIn('sumario_id', $sumarioIds))
            ->get(['solicitud_compra_item_id', 'decision_solicitante', 'cantidad'])
            ->groupBy('solicitud_compra_item_id');

        $acceptedQuantity = round((float) $ocItems
            ->flatten(1)
            ->where('decision_solicitante', 'ACEPTADO')
            ->sum('cantidad'), 2);

        $rejectedQuantity = round((float) $ocItems
            ->flatten(1)
            ->where('decision_solicitante', 'RECHAZADO')
            ->sum('cantidad'), 2);

        $hasAnyDecision = $ocItems
            ->flatten(1)
            ->contains(fn ($ocItem): bool => filled($ocItem->decision_solicitante));

        $allCompleted = $items->every(function (SolicitudCompraItem $item) use ($ocItems): bool {
            $cantidadObjetivo = round((float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);
            $cantidadAceptada = round((float) $ocItems
                ->get((int) $item->id, collect())
                ->where('decision_solicitante', 'ACEPTADO')
                ->sum('cantidad'), 2);

            return $cantidadObjetivo > 0 && $cantidadAceptada >= $cantidadObjetivo;
        });

        return [
            'accepted_quantity' => $acceptedQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'has_any_decision' => $hasAnyDecision,
            'all_completed' => $allCompleted,
        ];
    }

    private static function renderTrackingView(SolicitudCompra $record): string
    {
        $tracking = self::buildTrackingData($record);
        $summary = $tracking['summary'];
        $items = $tracking['items'];
        $sumarios = $tracking['sumarios'];

        $rows = collect($items)
            ->map(function (array $item): string {
                $secondaryStateHtml = filled($item['estado_secondary_label'] ?? null)
                    ? '<div style="margin-top:6px;"><span style="display:inline-block;padding:3px 7px;border-radius:9999px;font-size:10px;font-weight:600;background:' . e((string) ($item['estado_secondary_bg'] ?? '#fff7ed')) . ';color:' . e((string) ($item['estado_secondary_color'] ?? '#9a3412')) . ';">' . e((string) $item['estado_secondary_label']) . '</span></div>'
                    : '';

                return '<tr>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['item']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $item['descripcion']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">'
                    . '<span style="display:inline-block;padding:4px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:' . e((string) ($item['estado_bg'] ?? '#f3f4f6')) . ';color:' . e((string) ($item['estado_color'] ?? '#374151')) . ';">' . e((string) ($item['estado_label'] ?? 'Sin procesar')) . '</span>'
                    . $secondaryStateHtml
                    . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">'
                    . '<span style="display:inline-block;padding:4px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:' . e((string) ($item['cobertura_bg'] ?? '#f3f4f6')) . ';color:' . e((string) ($item['cobertura_color'] ?? '#374151')) . ';">' . e((string) ($item['cobertura_label'] ?? 'Pendiente')) . '</span>'
                    . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['en_cotizacion'] ?? '0,00')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['en_odc'] ?? '0,00')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['cantidad_pedida']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['entregados']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $item['faltantes']) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;font-weight:700;">' . e((string) $item['porcentaje']) . '%</td>'
                    . '</tr>';
            })
            ->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="10" style="border:1px solid #d1d5db;padding:10px;text-align:center;color:#6b7280;">Sin items registrados.</td></tr>';
        }

        $sumariosHtml = collect($sumarios)
            ->map(function (array $sumario): string {
                $ordenes = collect($sumario['ordenes'])
                    ->map(function (array $orden): string {
                        return '<li style="margin:4px 0;">' . e((string) $orden['label']) . '</li>';
                    })
                    ->implode('');

                if ($ordenes === '') {
                    $ordenes = '<li style="margin:4px 0;color:#6b7280;">Sin ordenes de compra generadas.</li>';
                }

                return '<div style="border:1px solid #d1d5db;border-radius:10px;padding:12px;background:#f9fafb;">'
                    . '<div style="font-size:14px;font-weight:700;color:#111827;">' . e((string) $sumario['label']) . '</div>'
                    . '<ul style="margin:8px 0 0 18px;padding:0;font-size:12px;color:#374151;">' . $ordenes . '</ul>'
                    . '</div>';
            })
            ->implode('');

        if ($sumariosHtml === '') {
            $sumariosHtml = '<div style="border:1px solid #d1d5db;border-radius:10px;padding:12px;background:#f9fafb;color:#6b7280;">Esta solicitud aun no tiene sumarios generados.</div>';
        }

        return '<div style="display:grid;gap:12px;">'
            . '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">'
            . self::trackingCard('Sumarios', (string) $summary['sumarios_count'])
            . self::trackingCard('ODC', (string) $summary['odcs_count'])
            . self::trackingCard('Items', (string) $summary['items_count'])
            . self::trackingCard('Avance general', (string) $summary['progress'] . '%')
            . '</div>'
            . '<div style="display:grid;gap:10px;">'
            . '<div style="font-size:13px;font-weight:700;color:#111827;">Sumarios y ordenes de compra asociadas</div>'
            . $sumariosHtml
            . '</div>'
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cobertura</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">En Cotización</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">En ODC</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad pedida</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Entregados</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Faltantes</th>'
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

    private static function renderFilesHubView(SolicitudCompra $record): string
    {
        $record->loadMissing([
            'sumarios.ordenesCompra',
        ]);

        $sumarios = $record->sumarios
            ->filter(fn (Sumario $sumario): bool => ! in_array((string) ($sumario->workflow_estado ?? $sumario->estado ?? ''), ['BORRADOR', 'RECHAZADO'], true)
                && ! in_array((string) ($sumario->estado ?? ''), ['BORRADOR', 'RECHAZADO'], true))
            ->sortBy(fn (Sumario $sumario): string => (string) ($sumario->correlativo_sdc ?: str_pad((string) $sumario->id, 10, '0', STR_PAD_LEFT)))
            ->values();

        $sumariosCount = $sumarios->count();
        $odcsCount = $sumarios->sum(fn (Sumario $sumario): int => (int) $sumario->ordenesCompra->count());
        $documentosCount = 1
            + $sumariosCount
            + $odcsCount
            + $sumarios->sum(function (Sumario $sumario): int {
                return $sumario->ordenesCompra->sum(function (OrdenCompra $ordenCompra): int {
                    $count = 0;

                    if (filled($ordenCompra->comprobante_pago_path)) {
                        $count++;
                    }

                    if (filled($ordenCompra->factura_path)) {
                        $count++;
                    }

                    return $count;
                });
            });

        $solicitudPdfUrl = route('solicitudes-compra.formato.print', ['solicitudCompra' => $record]);

        $sumariosHtml = $sumarios->map(function (Sumario $sumario): string {
            $sumarioPdfUrl = route('sumarios.formato.print', ['sumario' => $sumario]);

            $odcsHtml = $sumario->ordenesCompra
                ->sortBy(fn (OrdenCompra $ordenCompra): string => (string) ($ordenCompra->correlativo_odc ?: str_pad((string) $ordenCompra->id, 10, '0', STR_PAD_LEFT)))
                ->map(function (OrdenCompra $ordenCompra): string {
                    $odcPdfUrl = route('ordenes-compra.formato.print', ['ordenCompra' => $ordenCompra]);
                    $comprobanteUrl = filled($ordenCompra->comprobante_pago_path)
                        ? route('ordenes-compra.comprobante.download', ['ordenCompra' => $ordenCompra, 'inline' => 1])
                        : null;
                    $documentoRecepcionUrl = filled($ordenCompra->factura_path)
                        ? route('ordenes-compra.documento-recepcion.download', ['ordenCompra' => $ordenCompra, 'inline' => 1])
                        : null;
                    $documentoRecepcionLabel = strtoupper((string) ($ordenCompra->tipo_documento_recepcion ?? '')) === 'NOTA'
                        ? 'Ver nota de entrega'
                        : 'Ver factura';

                    $links = [
                        '<a href="' . e($odcPdfUrl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#1d4ed8;background:#eff6ff;">Vista PDF ODC</a>',
                    ];

                    if ($comprobanteUrl) {
                        $links[] = '<a href="' . e($comprobanteUrl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#065f46;background:#ecfdf5;">Ver comprobante</a>';
                    }

                    if ($documentoRecepcionUrl) {
                        $links[] = '<a href="' . e($documentoRecepcionUrl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#92400e;background:#fffbeb;">' . e($documentoRecepcionLabel) . '</a>';
                    }

                    $badges = [
                        '<span style="display:inline-block;padding:4px 8px;border-radius:9999px;background:#f3f4f6;color:#374151;font-size:11px;">Estado de la ODC: ' . e((string) ($ordenCompra->estado ?? 'N/A')) . '</span>',
                    ];

                    if (filled($ordenCompra->workflow_post_compra)) {
                        $badges[] = '<span style="display:inline-block;padding:4px 8px;border-radius:9999px;background:#eef2ff;color:#4338ca;font-size:11px;">Etapa del proceso: ' . e((string) $ordenCompra->workflow_post_compra) . '</span>';
                    }

                    if (! $comprobanteUrl) {
                        $badges[] = '<span style="display:inline-block;padding:4px 8px;border-radius:9999px;background:#fff7ed;color:#9a3412;font-size:11px;">Sin comprobante</span>';
                    }

                    if (! $documentoRecepcionUrl) {
                        $badges[] = '<span style="display:inline-block;padding:4px 8px;border-radius:9999px;background:#fef2f2;color:#b91c1c;font-size:11px;">Sin factura/nota</span>';
                    }

                    return '<div style="border:1px solid #dbeafe;border-radius:10px;padding:12px;background:#ffffff;display:grid;gap:10px;">'
                        . '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">'
                        . '<div>'
                        . '<div style="font-size:14px;font-weight:700;color:#0f172a;">ODC ' . e((string) ($ordenCompra->correlativo_odc ?: ('#' . $ordenCompra->id))) . '</div>'
                        . '<div style="font-size:12px;color:#64748b;">Proveedor: ' . e((string) ($ordenCompra->proveedor?->nombre ?? 'No definido')) . '</div>'
                        . '</div>'
                        . '<div style="display:flex;gap:6px;flex-wrap:wrap;">' . implode('', $badges) . '</div>'
                        . '</div>'
                        . '<div style="display:flex;gap:8px;flex-wrap:wrap;">' . implode('', $links) . '</div>'
                        . '</div>';
                })
                ->implode('');

            if ($odcsHtml === '') {
                $odcsHtml = '<div style="border:1px dashed #cbd5e1;border-radius:10px;padding:12px;color:#64748b;background:#f8fafc;">Este sumario aun no tiene ODC generadas.</div>';
            }

            return '<section style="border:1px solid #d1d5db;border-radius:12px;padding:14px;background:#f8fafc;display:grid;gap:12px;">'
                . '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">'
                . '<div>'
                . '<div style="font-size:15px;font-weight:700;color:#111827;">Sumario ' . e((string) ($sumario->correlativo_sdc ?: ('#' . $sumario->id))) . '</div>'
                . '<div style="font-size:12px;color:#6b7280;">Estado: ' . e((string) ($sumario->estado ?? 'N/A')) . '</div>'
                . '</div>'
                . '<a href="' . e($sumarioPdfUrl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#1d4ed8;background:#eff6ff;">Vista PDF Sumario</a>'
                . '</div>'
                . '<div style="display:grid;gap:10px;">' . $odcsHtml . '</div>'
                . '</section>';
        })->implode('');

        if ($sumariosHtml === '') {
            $sumariosHtml = '<div style="border:1px dashed #cbd5e1;border-radius:12px;padding:16px;background:#f8fafc;color:#64748b;">Esta solicitud aun no tiene sumarios ni ODC asociadas. Cuando se vayan generando, apareceran aqui.</div>';
        }

        return '<div style="display:grid;gap:14px;">'
            . '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">'
            . self::trackingCard('Solicitud PDF', '1')
            . self::trackingCard('Sumarios', (string) $sumariosCount)
            . self::trackingCard('Archivos detectados', (string) $documentosCount)
            . '</div>'
            . '<section style="border:1px solid #d1d5db;border-radius:12px;padding:14px;background:#ffffff;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">'
            . '<div>'
            . '<div style="font-size:15px;font-weight:700;color:#111827;">Solicitud ' . e((string) ($record->codigo_control ?: ('#' . $record->id))) . '</div>'
            . '<div style="font-size:12px;color:#6b7280;">N° usuario: ' . e((string) ($record->numero_solicitud_usuario ?: $record->id)) . ' | ODC totales: ' . e((string) $odcsCount) . '</div>'
            . '</div>'
            . '<a href="' . e($solicitudPdfUrl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#1d4ed8;background:#eff6ff;">Vista PDF Solicitud</a>'
            . '</section>'
            . '<div style="display:grid;gap:12px;">' . $sumariosHtml . '</div>'
            . '</div>';
    }

    /**
     * @return array{summary: array{sumarios_count:int,odcs_count:int,items_count:int,progress:int}, sumarios: array<int, array{label:string,ordenes: array<int, array{label:string}>}>, items: array<int, array<string, mixed>>}
     */
    private static function buildTrackingData(SolicitudCompra $record): array
    {
        $solicitudId = (int) $record->id;

        $items = SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->orderBy('item')
            ->get();

        $itemIds = $items->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        $ocItems = OrdenCompraItem::query()
            ->whereIn('solicitud_compra_item_id', $itemIds)
            ->whereHas('ordenCompra.sumario', fn ($query) => $query->where('solicitud_compra_id', $solicitudId))
            ->with('ordenCompra:id,correlativo_odc,sumario_id,workflow_post_compra')
            ->get()
            ->groupBy('solicitud_compra_item_id');

        $sumarios = Sumario::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->whereNotIn('workflow_estado', ['BORRADOR', 'RECHAZADO'])
            ->with(['ordenesCompra:id,sumario_id,correlativo_odc'])
            ->orderBy('id')
            ->get();

        $rows = [];
        $progressAccumulator = 0;

        foreach ($items as $item) {
            $itemOcRows = $ocItems->get($item->id, collect());

            $cantidadPedida = round((float) ($item->cantidad_pedida ?? $item->cantidad_a_comprar ?? $item->cantidad_solicitada ?? 0), 2);
            $cantidadComprada = round((float) ($item->cantidad_comprada ?? 0), 2);
            $cantidadEnSumario = round((float) ($item->cantidad_en_sumario ?? 0), 2);
            $cantidadEnOdc = round((float) $itemOcRows
                ->whereNull('decision_solicitante')
                ->sum('cantidad'), 2);
            $cantidadEnCotizacion = max(0, round($cantidadEnSumario - $cantidadComprada, 2));
            $cantidadEntregada = round((float) $itemOcRows
                ->where('decision_solicitante', 'ACEPTADO')
                ->sum('cantidad'), 2);
            $cantidadFaltante = max(0, round($cantidadPedida - $cantidadEntregada, 2));
            $progress = $cantidadPedida > 0
                ? (int) round(min(100, ($cantidadEntregada / $cantidadPedida) * 100))
                : 0;

            $estado = self::resolveTrackingItemState(
                $item,
                $itemOcRows,
                $cantidadPedida,
                $cantidadEntregada,
                $cantidadEnOdc,
                $cantidadEnCotizacion
            );
            $cobertura = self::resolveTrackingItemCoverage($cantidadPedida, $cantidadEntregada);

            $progressAccumulator += $progress;

            $rows[] = [
                'item' => $item->item ?: $item->id,
                'descripcion' => $item->descripcion,
                'estado_label' => $estado['label'],
                'estado_bg' => $estado['bg'],
                'estado_color' => $estado['color'],
                'estado_secondary_label' => $estado['secondary_label'] ?? null,
                'estado_secondary_bg' => $estado['secondary_bg'] ?? null,
                'estado_secondary_color' => $estado['secondary_color'] ?? null,
                'cobertura_label' => $cobertura['label'],
                'cobertura_bg' => $cobertura['bg'],
                'cobertura_color' => $cobertura['color'],
                'en_cotizacion' => number_format($cantidadEnCotizacion, 2, ',', '.'),
                'en_odc' => number_format($cantidadEnOdc, 2, ',', '.'),
                'cantidad_pedida' => number_format($cantidadPedida, 2, ',', '.'),
                'entregados' => number_format($cantidadEntregada, 2, ',', '.'),
                'faltantes' => number_format($cantidadFaltante, 2, ',', '.'),
                'porcentaje' => $progress,
            ];
        }

        $itemsCount = count($rows);
        $progress = $itemsCount > 0 ? (int) round($progressAccumulator / $itemsCount) : 0;

        return [
            'summary' => [
                'sumarios_count' => $sumarios->count(),
                'odcs_count' => $sumarios->sum(fn (Sumario $sumario): int => (int) $sumario->ordenesCompra->count()),
                'items_count' => $itemsCount,
                'progress' => $progress,
            ],
            'sumarios' => $sumarios
                ->map(function (Sumario $sumario): array {
                    return [
                        'label' => 'Sumario ' . (string) ($sumario->correlativo_sdc ?: ('#' . $sumario->id)),
                        'ordenes' => $sumario->ordenesCompra
                            ->map(fn (OrdenCompra $orden): array => [
                                'label' => (string) ($orden->correlativo_odc ?: ('OC #' . $orden->id)),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
            'items' => $rows,
        ];
    }

    /**
     * @return array{label:string,bg:string,color:string}
     */
    private static function resolveTrackingItemState(SolicitudCompraItem $item, $itemOcRows, float $cantidadPedida, float $cantidadEntregada, float $cantidadEnOdc, float $cantidadEnCotizacion): array
    {
        $hasRejected = $itemOcRows->contains(fn ($ocItem): bool => (string) ($ocItem->decision_solicitante ?? '') === 'RECHAZADO');
        $hasPaidOdc = $itemOcRows->contains(function ($ocItem): bool {
            $workflow = strtoupper((string) ($ocItem->ordenCompra?->workflow_post_compra ?? ''));

            return $workflow === 'PAGO_REGISTRADO_FINANZAS';
        });
        $hasPlannedReturn = $itemOcRows->contains(function ($ocItem): bool {
            $workflow = strtoupper((string) ($ocItem->ordenCompra?->workflow_post_compra ?? ''));

            return $workflow === 'DEVOLUCION_PLANIFICADA';
        });
        $hasReturnCompleted = $itemOcRows->contains(function ($ocItem): bool {
            $workflow = strtoupper((string) ($ocItem->ordenCompra?->workflow_post_compra ?? ''));

            return $workflow === 'DEVOLUCION_REALIZADA';
        });
        $inTransition = $itemOcRows->contains(fn ($ocItem): bool => (string) ($ocItem->estado_recepcion ?? '') === 'ZONA_TRANSICION');
        $hasOdc = $itemOcRows->isNotEmpty();
        $inSumario = round((float) ($item->cantidad_en_sumario ?? 0), 2) > 0;

        if ($cantidadPedida > 0 && $cantidadEntregada >= $cantidadPedida) {
            return ['label' => 'Entregado', 'bg' => '#ecfdf5', 'color' => '#166534'];
        }

        if ($cantidadEntregada > 0) {
            if ($inTransition) {
                if ($hasRejected) {
                    if ($hasPlannedReturn) {
                        return [
                            'label' => 'Entregado parcial',
                            'bg' => '#fffbeb',
                            'color' => '#92400e',
                            'secondary_label' => 'Devolucion planificada',
                            'secondary_bg' => '#fff7ed',
                            'secondary_color' => '#9a3412',
                        ];
                    }

                    if ($hasReturnCompleted) {
                        return [
                            'label' => 'Disponible en Almacen',
                            'bg' => '#ecfeff',
                            'color' => '#0f766e',
                            'secondary_label' => 'Devolucion realizada',
                            'secondary_bg' => '#fff7ed',
                            'secondary_color' => '#9a3412',
                        ];
                    }

                    return [
                        'label' => 'Entregado parcial',
                        'bg' => '#fffbeb',
                        'color' => '#92400e',
                        'secondary_label' => 'En espera devolucion',
                        'secondary_bg' => '#fff7ed',
                        'secondary_color' => '#9a3412',
                    ];
                }

                return [
                    'label' => 'Disponible en Almacen',
                    'bg' => '#ecfeff',
                    'color' => '#0f766e',
                    'secondary_label' => $hasReturnCompleted ? 'Devolucion realizada' : null,
                    'secondary_bg' => $hasReturnCompleted ? '#fff7ed' : null,
                    'secondary_color' => $hasReturnCompleted ? '#9a3412' : null,
                ];
            }

            if ($cantidadEnOdc > 0) {
                return ['label' => 'En Orden de Compra', 'bg' => '#eef2ff', 'color' => '#4338ca'];
            }

            if ($cantidadEnCotizacion > 0) {
                return ['label' => 'En Cotización', 'bg' => '#eff6ff', 'color' => '#1d4ed8'];
            }

            return [
                'label' => 'Entregado parcial',
                'bg' => '#fffbeb',
                'color' => '#92400e',
                'secondary_label' => $hasRejected ? 'En espera devolucion' : null,
                'secondary_bg' => $hasRejected ? '#fff7ed' : null,
                'secondary_color' => $hasRejected ? '#9a3412' : null,
            ];
        }

        if ($hasPlannedReturn) {
            return ['label' => 'Devolucion planificada', 'bg' => '#fff7ed', 'color' => '#9a3412'];
        }

        if ($hasReturnCompleted) {
            return ['label' => 'Devolucion realizada', 'bg' => '#ecfeff', 'color' => '#0f766e'];
        }

        if ($hasRejected) {
            return ['label' => 'Rechazado por solicitante', 'bg' => '#fef2f2', 'color' => '#b91c1c'];
        }

        if ($inTransition) {
            return ['label' => 'Disponible en Almacen', 'bg' => '#ecfeff', 'color' => '#0f766e'];
        }

        if ($hasPaidOdc) {
            return ['label' => 'ODC Pagada', 'bg' => '#ecfdf5', 'color' => '#166534'];
        }

        if ($cantidadEnOdc > 0 || $hasOdc) {
            return ['label' => 'En Orden de Compra', 'bg' => '#eef2ff', 'color' => '#4338ca'];
        }

        if ($cantidadEnCotizacion > 0 || $inSumario) {
            return ['label' => 'En Cotización', 'bg' => '#eff6ff', 'color' => '#1d4ed8'];
        }

        return ['label' => 'Sin procesar', 'bg' => '#f3f4f6', 'color' => '#374151'];
    }

    /**
     * @return array{label:string,bg:string,color:string}
     */
    private static function resolveTrackingItemCoverage(float $cantidadPedida, float $cantidadEntregada): array
    {
        if ($cantidadPedida > 0 && $cantidadEntregada >= $cantidadPedida) {
            return ['label' => 'Completo', 'bg' => '#ecfdf5', 'color' => '#166534'];
        }

        if ($cantidadEntregada > 0) {
            return ['label' => 'Parcial', 'bg' => '#fffbeb', 'color' => '#92400e'];
        }

        return ['label' => 'Pendiente', 'bg' => '#f3f4f6', 'color' => '#374151'];
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

