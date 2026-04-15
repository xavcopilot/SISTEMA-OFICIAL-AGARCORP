<?php

namespace App\Filament\Resources\SolicitudesCompra\Tables;

use App\Filament\Resources\AprobacionesCompra\AprobacionesCompraResource;
use App\Models\SolicitudCompra;
use App\Support\SolicitudCompraFlow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SolicitudesCompraTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
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

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => filled($state) ? str_replace('_', ' ', (string) $state) : '-')
                    ->color(fn ($state) => (string) $state === 'RECHAZADA' ? 'danger' : 'gray'),
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
                    ->modalHeading(fn (SolicitudCompra $record): string => 'Solicitud #' . $record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn (SolicitudCompra $record): array => self::getViewFormData($record))
                    ->schema(self::getViewSchema()),

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

    private static function getViewSchema(): array
    {
        return [
            Section::make('Resumen rapido')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextInput::make('id')
                                ->label('ID interno')
                                ->disabled(),

                            TextInput::make('codigo_control')
                                ->label('N° de control')
                                ->disabled(),
                            TextInput::make('estado')
                                ->label('Estado')
                                ->disabled(),
                            TextInput::make('fecha_solicitud')
                                ->label('Fecha solicitud')
                                ->disabled(),
                            TextInput::make('prioridad')
                                ->label('Prioridad')
                                ->disabled(),
                        ]),
                ]),

            Section::make('Datos de la solicitud')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('departamento_solicitante')
                                ->label('Departamento solicitante')
                                ->disabled(),
                            TextInput::make('tipo_solicitud')
                                ->label('Tipo de solicitud')
                                ->disabled(),
                            TextInput::make('solicitado_por_nombre')
                                ->label('Solicitado por')
                                ->disabled(),
                        ]),

                    Textarea::make('para_ser_usado_en')
                        ->label('Para ser usado en')
                        ->rows(2)
                        ->disabled(),
                ]),

            Section::make('Detalle de materiales / servicios')
                ->schema([
                    Placeholder::make('items_detalle')
                        ->label('Items')
                        ->content(fn (callable $get) => new HtmlString(self::renderItemsView($get('items') ?? [])))
                        ->dehydrated(false),
                ]),

            Section::make('Flujo y responsables')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Grid::make(1)
                                ->schema([
                                    TextInput::make('por_almacen_nombre')
                                        ->label('Por almacén')
                                        ->disabled(),
                                    TextInput::make('fecha_almacen')
                                        ->label('Fecha almacén')
                                        ->disabled(),
                                ]),
                            Grid::make(1)
                                ->schema([
                                    TextInput::make('aprobado_por_nombre')
                                        ->label('Aprobado por')
                                        ->disabled(),
                                    TextInput::make('fecha_aprobador')
                                        ->label('Fecha aprobador')
                                        ->disabled(),
                                ]),
                            Grid::make(1)
                                ->schema([
                                    TextInput::make('recibido_por_nombre')
                                        ->label('Recibido por')
                                        ->disabled(),
                                    TextInput::make('fecha_receptor')
                                        ->label('Fecha receptor')
                                        ->disabled(),
                                ]),
                            TextInput::make('hora_receptor')
                                ->label('Hora receptor')
                                ->disabled(),
                        ]),
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

        $cards = collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item, int $index): string {
                $itemNumber = e((string) ($item['item'] ?? $index + 1));
                $descripcion = e((string) ($item['descripcion'] ?? ''));
                $unidad = e((string) ($item['unidad_medida'] ?? ''));
                $solicitada = e((string) ($item['cantidad_solicitada'] ?? ''));
                $existencia = e((string) ($item['cantidad_existencia'] ?? ''));
                $comprar = e((string) ($item['cantidad_a_comprar'] ?? ''));

                return '<div class="sc-item-card" style="border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin-top:12px;background:#fff;">'
                    . '<div style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;">'
                    . self::renderItemField('Item', $itemNumber)
                    . self::renderItemField('Descripción', $descripcion)
                    . self::renderItemField('UND', $unidad)
                    . self::renderItemField('Solicitada', $solicitada)
                    . self::renderItemField('Existencia', $existencia)
                    . self::renderItemField('A comprar', $comprar)
                    . '</div>'
                    . '</div>';
            })
            ->implode('');

        return '<div x-data="{ expanded: false }">'
            . '<div style="display:flex;gap:16px;margin-bottom:8px;">'
            . '<button type="button" x-on:click="expanded = false" style="border:none;background:none;padding:0;color:#374151;font-size:14px;cursor:pointer;">Contraer todo</button>'
            . '<button type="button" x-on:click="expanded = true" style="border:none;background:none;padding:0;color:#374151;font-size:14px;cursor:pointer;">Expandir todo</button>'
            . '</div>'
                . '<div x-cloak x-show="expanded" x-transition.opacity>'
            . $cards
            . '</div>'
                . '<style>[x-cloak]{display:none!important;} @media (max-width: 1024px){.sc-item-card > div{grid-template-columns:repeat(2,minmax(0,1fr))!important;}}</style>'
            . '</div>';
    }

    private static function renderItemField(string $label, string $value): string
    {
        return '<div>'
            . '<div style="font-size:13px;font-weight:600;color:#111827;margin-bottom:6px;">' . e($label) . '</div>'
            . '<div style="min-height:44px;border:1px solid #d1d5db;border-radius:12px;padding:10px 14px;color:#6b7280;background:#fff;">' . $value . '</div>'
            . '</div>';
    }


    public static function configureForApprovals(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
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

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => filled($state) ? str_replace('_', ' ', (string) $state) : '-')
                    ->visible(fn ($livewire): bool => ! self::isApprovalHistoryTab($livewire))
                    ->color(fn ($state) => (string) $state === 'RECHAZADA' ? 'danger' : 'gray'),

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

                Action::make('verGestion')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (SolicitudCompra $record): string => AprobacionesCompraResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc');
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
}
