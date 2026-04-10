<?php

namespace App\Filament\Resources\Sumarios\Schemas;

use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SumarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('elaborado_por_user_id')
                    ->default(fn () => auth()->id()),

                Placeholder::make('sumario_sdc_style')
                    ->hiddenLabel()
                    ->content(new HtmlString('<style>
                        .sdc-sheet { border: 2px solid #0f172a; background: #f8fafc; }
                        .sdc-sheet .fi-section-header-heading { font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
                        .sdc-header, .sdc-proveedores, .sdc-items, .sdc-cuadro, .sdc-footer { border: 1px solid #1e293b; background: #fff; }
                        .sdc-header .fi-section-content, .sdc-proveedores .fi-section-content, .sdc-items .fi-section-content, .sdc-cuadro .fi-section-content, .sdc-footer .fi-section-content { padding: 12px; }
                        .sdc-header .fi-input-wrp, .sdc-proveedores .fi-input-wrp, .sdc-items .fi-input-wrp, .sdc-cuadro .fi-input-wrp, .sdc-footer .fi-input-wrp { border-radius: 0 !important; }
                        .sdc-cuadro [data-field-wrapper] { border: 1px solid #cbd5e1; padding: 6px; }
                        .sdc-cuadro .fi-fo-repeater-item { border: 1px solid #0f172a; border-radius: 0 !important; margin-bottom: 8px; }
                        .sdc-cuadro .fi-fo-repeater-item-header { background: #e2e8f0; border-bottom: 1px solid #94a3b8; }
                        .sdc-footer .fi-ta { min-height: 92px; }
                    </style>')),

                Section::make('FORMATO SUMARIO DE COTIZACIONES (ADV-FPR-SDC)')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-header'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('codigo_formato_sdc')
                                    ->label('Control de formato')
                                    ->content('COD: ADV-FPR-SDC | Revision: 01')
                                    ->columnSpan(6),
                                Placeholder::make('fecha_referencia_sdc')
                                    ->label('Referencia visual')
                                    ->content('Diseno operacional de Sumario de Cotizaciones')
                                    ->columnSpan(6),
                            ]),

                        Grid::make(12)
                            ->schema([
                                Select::make('solicitud_compra_id')
                                    ->label('Sumario N° / Solicitud de compra base')
                                    ->options(fn (): array => self::solicitudCompraOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $solicitud = filled($state) ? SolicitudCompra::find($state) : null;

                                        $set('departamento_solicitante', $solicitud?->departamento_solicitante);
                                        $set('selected_item_ids', []);
                                        $set('comparativo_items', []);
                                        self::setColumnTotals([], $set);
                                    })
                                    ->columnSpan(5),

                                TextInput::make('correlativo_sdc')
                                    ->label('Sumario N°')
                                    ->placeholder('2026-001')
                                    ->required()
                                    ->maxLength(50)
                                    ->columnSpan(3),

                                DatePicker::make('fecha')
                                    ->label('Fecha')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('procedencia')
                                    ->label('Procedencia de los Proveedores')
                                    ->options([
                                        'LOCAL' => 'Local',
                                        'IMPORTADO' => 'Importado',
                                    ])
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('tipo_orden')
                                    ->label('Tipo de Orden')
                                    ->options([
                                        'COMPRA' => 'Compra',
                                        'SERVICIO' => 'Servicio',
                                    ])
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('departamento_solicitante')
                                    ->label('Departamento Solicitante')
                                    ->required()
                                    ->readOnly()
                                    ->columnSpan(5),

                                TextInput::make('condiciones_pago')
                                    ->label('Condiciones de Pago')
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('tiempo_entrega')
                                    ->label('Tiempo de Entrega')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('PROVEEDORES')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-proveedores'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('proveedor_a_nombre')
                                    ->label('Proveedor 1')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                TextInput::make('proveedor_b_nombre')
                                    ->label('Proveedor 2')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                TextInput::make('proveedor_c_nombre')
                                    ->label('Proveedor 3')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                ToggleButtons::make('columna_ganadora')
                                    ->label('Proveedor ganador (prioridad visual)')
                                    ->options([
                                        'A' => 'Ganador: Proveedor 1',
                                        'B' => 'Ganador: Proveedor 2',
                                        'C' => 'Ganador: Proveedor 3',
                                    ])
                                    ->inline()
                                    ->grouped()
                                    ->nullable()
                                    ->live()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('ITEMS A INCLUIR EN EL SUMARIO')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-items'])
                    ->schema([
                        CheckboxList::make('selected_item_ids')
                            ->label('Seleccion parcial de items de la solicitud')
                            ->options(fn (callable $get): array => self::solicitudItemOptions((int) ($get('solicitud_compra_id') ?? 0)))
                            ->columns(1)
                            ->searchable()
                            ->bulkToggleable()
                            ->live()
                            ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                if (is_array($state) && $state !== []) {
                                    return;
                                }

                                $rows = $get('comparativo_items') ?? [];
                                $selected = collect($rows)
                                    ->filter(fn ($row) => is_array($row) && filled($row['solicitud_compra_item_id'] ?? null))
                                    ->pluck('solicitud_compra_item_id')
                                    ->map(fn ($id) => (string) $id)
                                    ->values()
                                    ->all();

                                if ($selected !== []) {
                                    $set('selected_item_ids', $selected);
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $selectedIds = collect($state ?? [])
                                    ->map(fn ($id) => (int) $id)
                                    ->filter(fn ($id) => $id > 0)
                                    ->values()
                                    ->all();

                                $existingRows = is_array($get('comparativo_items')) ? $get('comparativo_items') : [];
                                $solicitudId = (int) ($get('solicitud_compra_id') ?? 0);

                                self::syncRowsFromSelectedItems($selectedIds, $existingRows, $solicitudId, $set);
                            }),
                    ])
                    ->columnSpanFull(),

                Section::make('CUADRO COMPARATIVO DE COTIZACIONES')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-cuadro'])
                    ->description('Bloque principal del formato: item, descripcion, UND, cantidad y 3 proveedores con precios.')
                    ->schema([
                        Repeater::make('comparativo_items')
                            ->label('Matriz comparativa de items')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default([])
                            ->live()
                            ->afterStateHydrated(function ($state, callable $set): void {
                                $rows = self::recalculateRows($state ?? []);
                                $set('comparativo_items', $rows);
                                self::setColumnTotals($rows, $set);
                            })
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $rows = self::recalculateRows($state ?? []);
                                $set('comparativo_items', $rows);
                                self::setColumnTotals($rows, $set);
                            })
                            ->schema([
                                Grid::make(18)
                                    ->schema([
                                        TextInput::make('item')
                                            ->label('ITEM')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('descripcion')
                                            ->label('DESCRIPCION')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(4),

                                        TextInput::make('unidad_medida')
                                            ->label('UND')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('cantidad')
                                            ->label('CANT')
                                            ->disabled()
                                            ->dehydrated()
                                            ->numeric()
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov1')
                                            ->label('MARCA P1')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov1')
                                            ->label('PRECIO UNITARIO P1')
                                            ->numeric()
                                            ->default(0)
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov1', round($cantidad * $precioUnitario, 2));
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('precio_total_prov1')
                                            ->label('PRECIO TOTAL P1')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        TextInput::make('marca_prov2')
                                            ->label('MARCA P2')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov2')
                                            ->label('PRECIO UNITARIO P2')
                                            ->numeric()
                                            ->default(0)
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov2', round($cantidad * $precioUnitario, 2));
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('precio_total_prov2')
                                            ->label('PRECIO TOTAL P2')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        TextInput::make('marca_prov3')
                                            ->label('MARCA P3')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov3')
                                            ->label('PRECIO UNITARIO P3')
                                            ->numeric()
                                            ->default(0)
                                            ->live(debounce: 200)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $cantidad = (float) ($get('cantidad') ?? 0);
                                                $precioUnitario = (float) ($state ?? 0);

                                                $set('precio_total_prov3', round($cantidad * $precioUnitario, 2));
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('precio_total_prov3')
                                            ->label('PRECIO TOTAL P3')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        Select::make('proveedor_seleccionado')
                                            ->label('PROVEEDOR SELECCIONADO')
                                            ->options([
                                                'A' => 'Proveedor 1',
                                                'B' => 'Proveedor 2',
                                                'C' => 'Proveedor 3',
                                            ])
                                            ->required()
                                            ->default('A')
                                            ->columnSpan(3),

                                        Hidden::make('solicitud_compra_item_id'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('PIE DEL FORMATO')
                    ->extraAttributes(['class' => 'sdc-sheet sdc-footer'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('total_compra_prov1')
                                    ->label('TOTAL COMPRA PROVEEDOR 1')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(4),

                                TextInput::make('total_compra_prov2')
                                    ->label('TOTAL COMPRA PROVEEDOR 2')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(4),

                                TextInput::make('total_compra_prov3')
                                    ->label('TOTAL COMPRA PROVEEDOR 3')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(4),

                                Select::make('prioridad')
                                    ->label('PRIORIDAD / CARACTERISTICA DE LA COMPRA')
                                    ->options([
                                        'MEJOR_PRECIO' => 'MEJOR PRECIO',
                                        'CALIDAD' => 'MEJOR SERVICIO/CALIDAD',
                                    ])
                                    ->required()
                                    ->columnSpan(4),

                                Textarea::make('observaciones')
                                    ->label('OBSERVACIONES')
                                    ->rows(3)
                                    ->columnSpan(8),

                                Placeholder::make('elaborado_por_preview')
                                    ->label('Elaborado por')
                                    ->content(fn (): string => (string) (auth()->user()?->name ?? 'N/A'))
                                    ->columnSpan(4),

                                Placeholder::make('revisado_por_preview')
                                    ->label('Revisado por')
                                    ->content('Pendiente de revision de Finanzas')
                                    ->columnSpan(4),

                                Placeholder::make('firma_preview')
                                    ->label('Firma')
                                    ->content('Se registra en el flujo de aprobacion')
                                    ->columnSpan(4),

                                Hidden::make('proveedor_ganador_id'),
                                Hidden::make('estado'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function solicitudCompraOptions(): array
    {
        return SolicitudCompra::query()
            ->whereNotNull('fecha_receptor')
            ->where('estado', '!=', 'RECHAZADA')
            ->orderByDesc('id')
            ->get(['id', 'codigo_control', 'departamento_solicitante'])
            ->mapWithKeys(function (SolicitudCompra $solicitud): array {
                $codigo = $solicitud->codigo_control ?: (string) $solicitud->id;
                $label = $codigo . ' | ' . ($solicitud->departamento_solicitante ?: 'Sin departamento');

                return [$solicitud->id => $label];
            })
            ->all();
    }

    public static function solicitudItemOptions(int $solicitudId): array
    {
        if ($solicitudId <= 0) {
            return [];
        }

        return SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->orderBy('item')
            ->get()
            ->mapWithKeys(function (SolicitudCompraItem $item): array {
                $label = sprintf(
                    '#%s | %s | %s | Cant: %s',
                    (string) ($item->item ?: $item->id),
                    (string) $item->descripcion,
                    (string) $item->unidad_medida,
                    number_format((float) $item->cantidad_a_comprar ?: (float) $item->cantidad_solicitada, 2, ',', '.')
                );

                return [(string) $item->id => $label];
            })
            ->all();
    }

    public static function syncRowsFromSelectedItems(array $selectedIds, array $existingRows, int $solicitudId, callable $set): void
    {
        if ($solicitudId <= 0 || $selectedIds === []) {
            $set('comparativo_items', []);
            self::setColumnTotals([], $set);

            return;
        }

        $items = SolicitudCompraItem::query()
            ->where('solicitud_compra_id', $solicitudId)
            ->whereIn('id', $selectedIds)
            ->orderBy('item')
            ->get()
            ->keyBy('id');

        $existingByItemId = collect($existingRows)
            ->filter(fn ($row): bool => is_array($row) && filled($row['solicitud_compra_item_id'] ?? null))
            ->keyBy(fn ($row) => (int) $row['solicitud_compra_item_id']);

        $rows = [];

        foreach ($selectedIds as $selectedId) {
            $selectedId = (int) $selectedId;
            $item = $items->get($selectedId);

            if (! $item) {
                continue;
            }

            $existing = $existingByItemId->get($selectedId, []);
            $cantidad = (float) ($item->cantidad_a_comprar ?: $item->cantidad_solicitada);

            $rows[] = [
                'solicitud_compra_item_id' => $selectedId,
                'item' => $item->item ?: $item->id,
                'descripcion' => $item->descripcion,
                'unidad_medida' => $item->unidad_medida,
                'cantidad' => $cantidad,
                'marca_prov1' => $existing['marca_prov1'] ?? null,
                'precio_unitario_prov1' => (float) ($existing['precio_unitario_prov1'] ?? 0),
                'precio_total_prov1' => (float) ($existing['precio_total_prov1'] ?? 0),
                'marca_prov2' => $existing['marca_prov2'] ?? null,
                'precio_unitario_prov2' => (float) ($existing['precio_unitario_prov2'] ?? 0),
                'precio_total_prov2' => (float) ($existing['precio_total_prov2'] ?? 0),
                'marca_prov3' => $existing['marca_prov3'] ?? null,
                'precio_unitario_prov3' => (float) ($existing['precio_unitario_prov3'] ?? 0),
                'precio_total_prov3' => (float) ($existing['precio_total_prov3'] ?? 0),
                'proveedor_seleccionado' => $existing['proveedor_seleccionado'] ?? 'A',
            ];
        }

        $rows = self::recalculateRows($rows);

        $set('comparativo_items', $rows);
        self::setColumnTotals($rows, $set);
    }

    public static function recalculateRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                $cantidad = (float) ($row['cantidad'] ?? 0);

                $precioUnitario1 = (float) ($row['precio_unitario_prov1'] ?? 0);
                $precioUnitario2 = (float) ($row['precio_unitario_prov2'] ?? 0);
                $precioUnitario3 = (float) ($row['precio_unitario_prov3'] ?? 0);

                $row['precio_total_prov1'] = round($cantidad * $precioUnitario1, 2);
                $row['precio_total_prov2'] = round($cantidad * $precioUnitario2, 2);
                $row['precio_total_prov3'] = round($cantidad * $precioUnitario3, 2);

                return $row;
            })
            ->values()
            ->all();
    }

    public static function setColumnTotals(array $rows, callable $set): void
    {
        $totalProv1 = 0.0;
        $totalProv2 = 0.0;
        $totalProv3 = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $totalProv1 += (float) ($row['precio_total_prov1'] ?? 0);
            $totalProv2 += (float) ($row['precio_total_prov2'] ?? 0);
            $totalProv3 += (float) ($row['precio_total_prov3'] ?? 0);
        }

        $set('total_compra_prov1', round($totalProv1, 2));
        $set('total_compra_prov2', round($totalProv2, 2));
        $set('total_compra_prov3', round($totalProv3, 2));
    }
}
