<?php

namespace App\Filament\Resources\Sumarios\Schemas;

use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SumarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('elaborado_por_user_id')
                    ->default(fn () => auth()->id()),

                Section::make('Cabecera del sumario (ADV-FPR-SDC)')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Select::make('solicitud_compra_id')
                                    ->label('Solicitud de compra')
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
                                    ->columnSpan(4),

                                TextInput::make('correlativo_sdc')
                                    ->label('Correlativo SDC')
                                    ->placeholder('2026-001')
                                    ->required()
                                    ->maxLength(50)
                                    ->columnSpan(2),

                                DatePicker::make('fecha')
                                    ->label('Fecha')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('procedencia')
                                    ->label('Procedencia')
                                    ->options([
                                        'LOCAL' => 'Local',
                                        'IMPORTADO' => 'Importado',
                                    ])
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('tipo_orden')
                                    ->label('Tipo de orden')
                                    ->options([
                                        'COMPRA' => 'Compra',
                                        'SERVICIO' => 'Servicio',
                                    ])
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('departamento_solicitante')
                                    ->label('Departamento solicitante')
                                    ->required()
                                    ->readOnly()
                                    ->columnSpan(4),

                                TextInput::make('condiciones_pago')
                                    ->label('Condiciones de pago')
                                    ->maxLength(255)
                                    ->columnSpan(4),

                                TextInput::make('tiempo_entrega')
                                    ->label('Tiempo de entrega')
                                    ->maxLength(255)
                                    ->columnSpan(4),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Proveedores comparativos')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('proveedor_a_nombre')
                                    ->label('Proveedor A')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                TextInput::make('proveedor_b_nombre')
                                    ->label('Proveedor B')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                TextInput::make('proveedor_c_nombre')
                                    ->label('Proveedor C')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->columnSpan(4),

                                ToggleButtons::make('columna_ganadora')
                                    ->label('Marcar proveedor ganador')
                                    ->options([
                                        'A' => 'Ganador: Proveedor A',
                                        'B' => 'Ganador: Proveedor B',
                                        'C' => 'Ganador: Proveedor C',
                                    ])
                                    ->inline()
                                    ->grouped()
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Seleccion parcial de items de la solicitud')
                    ->schema([
                        CheckboxList::make('selected_item_ids')
                            ->label('Items a incluir en este sumario')
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

                Section::make('Cuadro comparativo de cotizaciones')
                    ->description('Izquierda: descripcion/unidad/cantidad. Centro: columnas de Proveedor A, B y C con calculo automatico.')
                    ->schema([
                        Repeater::make('comparativo_items')
                            ->label('Items del sumario')
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
                                            ->label('Item')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('descripcion')
                                            ->label('Descripcion')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(4),

                                        TextInput::make('unidad_medida')
                                            ->label('UND')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->disabled()
                                            ->dehydrated()
                                            ->numeric()
                                            ->columnSpan(1),

                                        TextInput::make('marca_prov1')
                                            ->label('Marca A')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov1')
                                            ->label('P. Unit A')
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
                                            ->label('P. Total A')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        TextInput::make('marca_prov2')
                                            ->label('Marca B')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov2')
                                            ->label('P. Unit B')
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
                                            ->label('P. Total B')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        TextInput::make('marca_prov3')
                                            ->label('Marca C')
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('precio_unitario_prov3')
                                            ->label('P. Unit C')
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
                                            ->label('P. Total C')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        Hidden::make('solicitud_compra_item_id'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Totales y decision')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('total_compra_prov1')
                                    ->label('Total compra Proveedor A')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(4),

                                TextInput::make('total_compra_prov2')
                                    ->label('Total compra Proveedor B')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(4),

                                TextInput::make('total_compra_prov3')
                                    ->label('Total compra Proveedor C')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(4),

                                Select::make('prioridad')
                                    ->label('Prioridad de decision')
                                    ->options([
                                        'MEJOR_PRECIO' => 'Mejor Precio',
                                        'CALIDAD' => 'Calidad',
                                    ])
                                    ->required()
                                    ->columnSpan(4),

                                Textarea::make('observaciones')
                                    ->label('Observaciones')
                                    ->rows(3)
                                    ->columnSpan(8),

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
