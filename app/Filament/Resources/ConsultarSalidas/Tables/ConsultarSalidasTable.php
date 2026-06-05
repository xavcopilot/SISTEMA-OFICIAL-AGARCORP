<?php

namespace App\Filament\Resources\ConsultarSalidas\Tables;

use App\Models\Departamento;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Support\InventoryMovementLineEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ConsultarSalidasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('tipo', 'salida')
                ->with(['items.product.subcategory.category'])
                ->orderByDesc('fecha')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('nro_control')
                    ->label('N° Control')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('dpto_responsable')
                    ->label('Dpto Responsable')
                    ->state(fn (InventoryMovement $record): string => (string) ($record->dpto_responsable_unificado ?? ''))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('almacenista')
                    ->label('Quién entrega')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('total_items')
                    ->label('Total Items')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('comentarios')
                    ->label('Observaciones')
                    ->limit(45)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('fecha')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Desde'),
                        DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('fecha', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('fecha', '<=', $date));
                    }),

                Filter::make('dpto_responsable')
                    ->label('Dpto Responsable')
                    ->schema([
                        TextInput::make('dpto_responsable')
                            ->label('Dpto Responsable'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $dptoResponsable = trim((string) ($data['dpto_responsable'] ?? ''));

                        if ($dptoResponsable === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($dptoResponsable): void {
                            $subQuery
                                ->where('dpto_responsable', 'like', '%' . $dptoResponsable . '%')
                                ->orWhere('dpto_destino', 'like', '%' . $dptoResponsable . '%')
                                ->orWhere('responsable_destino', 'like', '%' . $dptoResponsable . '%');
                        });
                    }),

                Filter::make('nro_control')
                    ->label('N° Control')
                    ->schema([
                        TextInput::make('nro_control')
                            ->label('N° Control'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $control = trim((string) ($data['nro_control'] ?? ''));

                        if ($control === '') {
                            return $query;
                        }

                        return $query->where('nro_control', 'like', '%' . $control . '%');
                    }),
            ])
            ->recordActions([
                Action::make('verDetalle')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (InventoryMovement $record): string => 'Salida ' . $record->nro_control)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn (InventoryMovement $record): array => self::getViewFormData($record))
                    ->schema(self::getViewSchema()),

                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('Update:InventoryMovement'))
                    ->modalHeading(fn (InventoryMovement $record): string => 'Editar Salida ' . $record->nro_control)
                    ->fillForm(fn (InventoryMovement $record): array => self::getEditSalidaFormData($record))
                    ->schema(self::getEditSalidaSchema())
                    ->action(function (array $data, InventoryMovement $record): void {
                        InventoryMovementLineEditor::updateSalida(
                            $record,
                            [
                                'almacenista' => $data['almacenista'] ?? null,
                                'dpto_responsable' => $data['dpto_responsable'] ?? null,
                                'responsable_destino' => null,
                                'dpto_destino' => $data['dpto_responsable'] ?? null,
                                'comentarios' => $data['comentarios'] ?? null,
                            ],
                            $data['items'] ?? []
                        );

                        Notification::make()
                            ->title('Salida actualizada con ajuste de lineas')
                            ->success()
                            ->send();
                    }),

                Action::make('verFormato')
                    ->label('Ver formato')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn (InventoryMovement $record): string => route('inventario.movimientos.formato-salida', ['inventoryMovement' => $record, 'download' => 1]))
                    ->openUrlInNewTab(),
            ])
            ->recordUrl(null);
    }

    private static function getEditSalidaSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('almacenista')
                        ->label('Quien entrega (Almacenista)')
                        ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'name')->toArray())
                        ->searchable()
                        ->required(),

                    Select::make('dpto_responsable')
                        ->label('Dpto Responsable')
                        ->options(fn (): array => Departamento::query()->orderBy('nombre')->pluck('nombre', 'nombre')->toArray())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->columnSpan(2),
                ]),

            Section::make('Lineas de materiales')
                ->collapsible()
                ->schema([
                    Repeater::make('items')
                        ->label('Lineas de materiales')
                        ->defaultItems(0)
                        ->minItems(1)
                        ->maxItems(13)
                        ->addable(fn (callable $get): bool => count($get('items') ?? []) < 13)
                        ->addActionLabel('Agregar linea')
                        ->deletable(false)
                        ->helperText('Maximo 13 articulos por salida.')
                        ->schema([
                            Hidden::make('movement_item_id'),

                            Select::make('product_id_by_description')
                                ->label('Buscar por descripcion')
                                ->options(fn (): array => Product::query()
                                    ->where('is_archived', false)
                                    ->orderBy('descripcion')
                                    ->get(['id', 'descripcion', 'sku', 'stock_actual'])
                                    ->mapWithKeys(fn (Product $product): array => [
                                        $product->id => (string) ($product->descripcion ?? '-')
                                            . ' | SKU: ' . (string) ($product->sku ?? '-')
                                            . ' | Disp: ' . (int) ($product->stock_actual ?? 0),
                                    ])
                                    ->toArray())
                                ->searchable()
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                    $set('product_id_by_description', $get('product_id'));
                                })
                                ->afterStateUpdated(function ($state, callable $set): void {
                                    $set('product_id', $state ? (int) $state : null);
                                    $set('precio_momento', (float) (Product::query()->whereKey($state)->value('precio_unitario') ?? 0));
                                })
                                ->helperText('Busca por descripcion y veras SKU + unidades disponibles antes de seleccionar.'),

                            Select::make('product_id')
                                ->label('SKU')
                                ->options(fn (): array => Product::query()->where('is_archived', false)->orderBy('sku')->pluck('sku', 'id')->toArray())
                                ->searchable()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set): void {
                                    $set('product_id_by_description', $state ? (int) $state : null);
                                    $set('precio_momento', (float) (Product::query()->whereKey($state)->value('precio_unitario') ?? 0));
                                })
                                ->helperText(function (callable $get): ?string {
                                    $product = Product::query()->find((int) ($get('product_id') ?? 0));

                                    if (! $product) {
                                        return null;
                                    }

                                    return 'Disponible: ' . (int) ($product->stock_actual ?? 0) . ' | SKU: ' . (string) ($product->sku ?? '-');
                                })
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            Hidden::make('precio_momento'),

                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->minValue(1)
                                ->required(),

                            Select::make('retorna')
                                ->label('Retorna')
                                ->options([
                                    '1' => 'SI',
                                    '0' => 'NO',
                                ])
                                ->default('0')
                                ->required(),

                            TextInput::make('observaciones_item')
                                ->label('Observacion de item')
                                ->maxLength(500),

                            Toggle::make('eliminar_linea')
                                ->label('Eliminar linea')
                                ->default(false)
                                ->live(),

                            Select::make('motivo_eliminacion')
                                ->label('Motivo de eliminacion')
                                ->options(InventoryMovementLineEditor::removalReasonOptions())
                                ->visible(fn (callable $get): bool => (bool) $get('eliminar_linea'))
                                ->required(fn (callable $get): bool => (bool) $get('eliminar_linea')),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            Textarea::make('comentarios')
                ->label('Observaciones')
                ->rows(3)
                ->maxLength(2000),
        ];
    }

    private static function getEditSalidaFormData(InventoryMovement $record): array
    {
        $record->loadMissing('items.product');

        return [
            'almacenista' => $record->almacenista,
            'dpto_responsable' => $record->dpto_responsable_unificado,
            'comentarios' => $record->comentarios,
            'items' => $record->items->map(function ($item): array {
                return [
                    'movement_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'cantidad' => (int) $item->cantidad,
                    'precio_momento' => (float) ($item->precio_momento ?? 0),
                    'retorna' => $item->retorna ? '1' : '0',
                    'observaciones_item' => $item->observaciones_item,
                    'eliminar_linea' => false,
                    'motivo_eliminacion' => null,
                ];
            })->values()->all(),
        ];
    }

    private static function getViewSchema(): array
    {
        return [
            Section::make('Datos generales')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextInput::make('nro_control')
                                ->label('N° Control')
                                ->disabled(),
                            TextInput::make('fecha')
                                ->label('Fecha')
                                ->disabled(),
                            TextInput::make('total_items')
                                ->label('Total Items')
                                ->disabled(),
                            TextInput::make('almacenista')
                                ->label('Quién entrega')
                                ->disabled(),
                            TextInput::make('dpto_responsable')
                                ->label('Dpto Responsable')
                                ->disabled()
                                ->columnSpan(2),
                        ]),
                ]),
            Section::make('Detalle de artículos')
                ->schema([
                    Placeholder::make('items_detalle')
                        ->label('Items')
                        ->content(fn (callable $get) => new HtmlString(self::renderItemsView($get('items') ?? [])))
                        ->dehydrated(false),
                ]),
            Section::make('Observaciones')
                ->schema([
                    TextInput::make('comentarios')
                        ->label('Observaciones')
                        ->disabled(),
                ]),
        ];
    }

    private static function getViewFormData(InventoryMovement $record): array
    {
        return [
            'nro_control' => $record->nro_control,
            'fecha' => $record->fecha?->format('d/m/Y'),
            'total_items' => $record->total_items,
            'almacenista' => $record->almacenista,
            'dpto_responsable' => $record->dpto_responsable_unificado,
            'comentarios' => $record->comentarios,
            'items' => $record->items->map(function ($item): array {
                return [
                    'sku' => $item->product?->sku,
                    'descripcion' => $item->product?->descripcion,
                    'categoria' => $item->product?->subcategory?->category?->name,
                    'subcategoria' => $item->product?->subcategory?->name,
                    'cantidad' => $item->cantidad,
                    'ubicacion' => $item->product?->ubicacion,
                    'retorna' => $item->retorna ? 'Si' : 'No',
                    'observaciones_item' => $item->observaciones_item,
                ];
            })->values()->all(),
        ];
    }

    private static function renderItemsView(array $items): string
    {
        if ($items === []) {
            return '<div style="padding:12px 0;color:#6b7280;">Sin items registrados.</div>';
        }

        $rows = collect($items)
            ->map(function (array $item): string {
                return '<tr>'
                    . self::renderTableCell((string) ($item['sku'] ?? '-'))
                    . self::renderTableCell((string) ($item['descripcion'] ?? '-'))
                    . self::renderTableCell((string) ($item['categoria'] ?? '-'))
                    . self::renderTableCell((string) ($item['subcategoria'] ?? '-'))
                    . self::renderTableCell((string) ($item['cantidad'] ?? '-'))
                    . self::renderTableCell((string) ($item['ubicacion'] ?? '-'))
                    . self::renderTableCell((string) ($item['retorna'] ?? '-'))
                    . self::renderTableCell((string) ($item['observaciones_item'] ?? '-'))
                    . '</tr>';
            })
            ->implode('');

        return '<div style="overflow-x:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . self::renderHeaderCell('SKU')
            . self::renderHeaderCell('Descripción')
            . self::renderHeaderCell('Categoría')
            . self::renderHeaderCell('Subcat')
            . self::renderHeaderCell('Cant')
            . self::renderHeaderCell('Ubicación')
            . self::renderHeaderCell('Retorna')
            . self::renderHeaderCell('Obs. Item')
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>';
    }

    private static function renderHeaderCell(string $label): string
    {
        return '<th style="border:1px solid #d1d5db;padding:8px;text-align:left;font-weight:600;white-space:nowrap;">' . e($label) . '</th>';
    }

    private static function renderTableCell(string $value): string
    {
        return '<td style="border:1px solid #e5e7eb;padding:8px;vertical-align:top;">' . e($value) . '</td>';
    }
}

