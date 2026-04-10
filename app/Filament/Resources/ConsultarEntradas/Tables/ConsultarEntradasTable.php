<?php

namespace App\Filament\Resources\ConsultarEntradas\Tables;

use App\Models\Category;
use App\Models\Departamento;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Subcategory;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ConsultarEntradasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereIn('tipo', ['ingreso', 'entrada'])
                ->with(['items.product'])
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

                TextColumn::make('nro_solicitud')
                    ->label('N° Solicitud')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('orden_compra')
                    ->label('Orden de Compra')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('factura_nota')
                    ->label('F/N/I')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('nro_doc_legal')
                    ->label('N°')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('proveedor')
                    ->label('Proveedor')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('total_items')
                    ->label('Total Items')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('comentarios')
                    ->label('Comentarios')
                    ->limit(40)
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

                Filter::make('proveedor')
                    ->label('Proveedor')
                    ->schema([
                        TextInput::make('proveedor')
                            ->label('Proveedor'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $proveedor = trim((string) ($data['proveedor'] ?? ''));

                        if ($proveedor === '') {
                            return $query;
                        }

                        return $query->where('proveedor', 'like', '%' . $proveedor . '%');
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

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'ingreso' => 'Ingreso',
                        'entrada' => 'Entrada',
                    ]),
            ])
            ->recordActions([
                Action::make('verDetalle')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (InventoryMovement $record): string => 'Entrada ' . $record->nro_control)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn (InventoryMovement $record): array => self::getViewFormData($record))
                    ->schema(self::getViewSchema()),

                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (InventoryMovement $record): bool => (bool) auth()->user()?->can('Update:InventoryMovement') && in_array((string) $record->tipo, ['entrada', 'ingreso'], true))
                    ->modalHeading(fn (InventoryMovement $record): string => 'Editar ' . ucfirst((string) $record->tipo) . ' ' . $record->nro_control)
                    ->fillForm(fn (InventoryMovement $record): array => (string) $record->tipo === 'ingreso'
                        ? self::getEditIngresoFormData($record)
                        : self::getEditEntradaFormData($record))
                    ->schema(fn (InventoryMovement $record): array => (string) $record->tipo === 'ingreso'
                        ? self::getEditIngresoSchema()
                        : self::getEditEntradaSchema())
                    ->action(function (array $data, InventoryMovement $record): void {
                        $movementPayload = [
                            'almacenista' => $data['almacenista'] ?? null,
                            'orden_compra' => $data['orden_compra'] ?? null,
                            'nro_solicitud' => $data['nro_solicitud'] ?? null,
                            'factura_nota' => $data['factura_nota'] ?? null,
                            'nro_doc_legal' => $data['nro_doc_legal'] ?? null,
                            'proveedor' => $data['proveedor'] ?? null,
                            'comentarios' => $data['comentarios'] ?? null,
                        ];

                        if ((string) $record->tipo === 'ingreso') {
                            InventoryMovementLineEditor::updateIngreso($record, $movementPayload, $data['items'] ?? []);
                        } else {
                            InventoryMovementLineEditor::updateEntrada($record, $movementPayload, $data['items'] ?? []);
                        }

                        Notification::make()
                            ->title(ucfirst((string) $record->tipo) . ' actualizada con ajuste de lineas')
                            ->success()
                            ->send();
                    }),

                Action::make('verFormato')
                    ->label('Ver formato')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn (InventoryMovement $record): string => route('inventario.movimientos.formato-entrada', ['inventoryMovement' => $record, 'download' => 1]))
                    ->openUrlInNewTab(),
            ])
            ->recordUrl(null);
    }

    private static function getEditEntradaSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('almacenista')
                        ->label('Almacenista')
                        ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'name')->toArray())
                        ->searchable()
                        ->required(),

                    TextInput::make('proveedor')
                        ->label('Proveedor')
                        ->maxLength(255)
                        ->columnSpan(2),

                    TextInput::make('orden_compra')
                        ->label('Orden de Compra')
                        ->maxLength(255),

                    TextInput::make('nro_solicitud')
                        ->label('N Solicitud')
                        ->maxLength(255),

                    TextInput::make('factura_nota')
                        ->label('F/N/I')
                        ->maxLength(255),

                    TextInput::make('nro_doc_legal')
                        ->label('N Doc. Legal')
                        ->maxLength(255),
                ]),

            Section::make('Lineas de materiales')
                ->collapsible()
                ->schema([
                    Repeater::make('items')
                        ->label('Lineas de materiales')
                        ->defaultItems(0)
                        ->minItems(1)
                        ->maxItems(12)
                        ->addable(fn (callable $get): bool => count($get('items') ?? []) < 12)
                        ->addActionLabel('Agregar linea')
                        ->deletable(false)
                        ->helperText('Maximo 12 articulos por entrada.')
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
                                })
                                ->helperText(function (callable $get): ?string {
                                    $product = Product::query()->find((int) ($get('product_id') ?? 0));

                                    if (! $product) {
                                        return null;
                                    }

                                    return 'Disponible: ' . (int) ($product->stock_actual ?? 0) . ' | SKU: ' . (string) ($product->sku ?? '-');
                                })
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->minValue(1)
                                ->required(),

                            TextInput::make('precio_momento')
                                ->label('Precio')
                                ->numeric()
                                ->minValue(0)
                                ->required(),

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
                ->label('Comentarios')
                ->rows(3)
                ->maxLength(2000),
        ];
    }

    private static function getEditIngresoSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('almacenista')
                        ->label('Almacenista')
                        ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'name')->toArray())
                        ->searchable()
                        ->required(),

                    TextInput::make('proveedor')
                        ->label('Proveedor')
                        ->maxLength(255)
                        ->columnSpan(2),

                    TextInput::make('orden_compra')
                        ->label('Orden de Compra')
                        ->maxLength(255),

                    TextInput::make('nro_solicitud')
                        ->label('N Solicitud')
                        ->maxLength(255),

                    TextInput::make('factura_nota')
                        ->label('F/N/I')
                        ->maxLength(255),

                    TextInput::make('nro_doc_legal')
                        ->label('N Doc. Legal')
                        ->maxLength(255),
                ]),

            Section::make('Lineas de ingreso')
                ->collapsible()
                ->schema([
                    Repeater::make('items')
                        ->label('Lineas de ingreso')
                        ->defaultItems(0)
                        ->minItems(1)
                        ->addActionLabel('Agregar linea')
                        ->deletable(false)
                        ->schema([
                            Hidden::make('movement_item_id'),
                            Hidden::make('product_id'),

                            TextInput::make('sku')
                                ->label('SKU')
                                ->disabled()
                                ->dehydrated()
                                ->helperText('Se genera automaticamente segun la categoria seleccionada.')
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            Select::make('category_id')
                                ->label('Categoria')
                                ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->live()
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea'))
                                ->afterStateUpdated(function (?int $state, callable $set): void {
                                    $set('subcategory_id', null);
                                    $set('sku', Product::previewSkuForCategoryId($state));
                                }),

                            Select::make('subcategory_id')
                                ->label('Sub Categoria')
                                ->options(function (callable $get): array {
                                    $categoryId = (int) ($get('category_id') ?? 0);

                                    if ($categoryId <= 0) {
                                        return [];
                                    }

                                    return Subcategory::query()
                                        ->where('category_id', $categoryId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('descripcion')
                                ->label('Descripcion')
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('marca')
                                ->label('Marca')
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('serial')
                                ->label('Serial'),

                            TextInput::make('estado')
                                ->label('Estado')
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('medida')
                                ->label('Medida')
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('ubicacion')
                                ->label('Ubicacion')
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('dpto_responsable')
                                ->label('Dpto Responsable')
                                ->datalist(fn (): array => Departamento::query()->orderBy('nombre')->pluck('nombre')->all())
                                ->helperText('Puedes escribir libremente o elegir un departamento sugerido.')
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('stock_minimo')
                                ->label('Rango minimo')
                                ->numeric()
                                ->minValue(0)
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->minValue(1)
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

                            TextInput::make('precio_momento')
                                ->label('Precio')
                                ->numeric()
                                ->minValue(0)
                                ->required(fn (callable $get): bool => ! (bool) $get('eliminar_linea')),

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
                ->label('Comentarios')
                ->rows(3)
                ->maxLength(2000),
        ];
    }

    private static function getEditEntradaFormData(InventoryMovement $record): array
    {
        $record->loadMissing('items.product');

        return [
            'almacenista' => $record->almacenista,
            'orden_compra' => $record->orden_compra,
            'nro_solicitud' => $record->nro_solicitud,
            'factura_nota' => $record->factura_nota,
            'nro_doc_legal' => $record->nro_doc_legal,
            'proveedor' => $record->proveedor,
            'comentarios' => $record->comentarios,
            'items' => $record->items->map(function ($item): array {
                return [
                    'movement_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'cantidad' => (int) $item->cantidad,
                    'precio_momento' => (float) $item->precio_momento,
                    'eliminar_linea' => false,
                    'motivo_eliminacion' => null,
                ];
            })->values()->all(),
        ];
    }

    private static function getEditIngresoFormData(InventoryMovement $record): array
    {
        $record->loadMissing('items.product.subcategory');

        return [
            'almacenista' => $record->almacenista,
            'orden_compra' => $record->orden_compra,
            'nro_solicitud' => $record->nro_solicitud,
            'factura_nota' => $record->factura_nota,
            'nro_doc_legal' => $record->nro_doc_legal,
            'proveedor' => $record->proveedor,
            'comentarios' => $record->comentarios,
            'items' => $record->items->map(function ($item): array {
                return [
                    'movement_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku' => (string) ($item->product?->sku ?? ''),
                    'category_id' => (int) ($item->product?->subcategory?->category_id ?? 0),
                    'subcategory_id' => (int) ($item->product?->subcategory_id ?? 0),
                    'descripcion' => (string) ($item->product?->descripcion ?? ''),
                    'marca' => (string) ($item->product?->marca ?? ''),
                    'serial' => (string) ($item->product?->serial ?? ''),
                    'estado' => (string) ($item->product?->estado ?? ''),
                    'medida' => (string) ($item->product?->medida ?? ''),
                    'ubicacion' => (string) ($item->product?->ubicacion ?? ''),
                    'dpto_responsable' => (string) ($item->product?->dpto_responsable ?? ''),
                    'stock_minimo' => (int) ($item->product?->stock_minimo ?? 0),
                    'cantidad' => (int) ($item->cantidad ?? 0),
                    'precio_momento' => (float) ($item->precio_momento ?? 0),
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
                            TextInput::make('tipo')
                                ->label('Tipo')
                                ->disabled(),
                            TextInput::make('fecha')
                                ->label('Fecha')
                                ->disabled(),
                            TextInput::make('total_items')
                                ->label('Total Items')
                                ->disabled(),
                            TextInput::make('nro_solicitud')
                                ->label('N° Solicitud')
                                ->disabled(),
                            TextInput::make('orden_compra')
                                ->label('Orden de Compra')
                                ->disabled(),
                            TextInput::make('factura_nota')
                                ->label('F/N/I')
                                ->disabled(),
                            TextInput::make('nro_doc_legal')
                                ->label('N°')
                                ->disabled(),
                            TextInput::make('proveedor')
                                ->label('Proveedor')
                                ->disabled()
                                ->columnSpan(2),
                            TextInput::make('almacenista')
                                ->label('Almacenista')
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
            Section::make('Comentarios')
                ->schema([
                    TextInput::make('comentarios')
                        ->label('Comentarios')
                        ->disabled(),
                ]),
        ];
    }

    private static function getViewFormData(InventoryMovement $record): array
    {
        return [
            'nro_control' => $record->nro_control,
            'tipo' => ucfirst((string) $record->tipo),
            'fecha' => $record->fecha?->format('d/m/Y'),
            'total_items' => $record->total_items,
            'nro_solicitud' => $record->nro_solicitud,
            'orden_compra' => $record->orden_compra,
            'factura_nota' => $record->factura_nota,
            'nro_doc_legal' => $record->nro_doc_legal,
            'proveedor' => $record->proveedor,
            'almacenista' => $record->almacenista,
            'comentarios' => $record->comentarios,
            'items' => $record->items->map(function ($item): array {
                return [
                    'sku' => $item->product?->sku,
                    'descripcion' => $item->product?->descripcion,
                    'marca' => $item->product?->marca,
                    'categoria' => $item->product?->subcategory?->category?->name,
                    'subcategoria' => $item->product?->subcategory?->name,
                    'serial' => $item->product?->serial,
                    'estado' => $item->product?->estado,
                    'medida' => $item->product?->medida,
                    'cantidad' => $item->cantidad,
                    'ubicacion' => $item->product?->ubicacion,
                    'responsable' => $item->product?->dpto_responsable,
                    'precio' => $item->precio_momento,
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
                    . self::renderTableCell((string) ($item['marca'] ?? '-'))
                    . self::renderTableCell((string) ($item['categoria'] ?? '-'))
                    . self::renderTableCell((string) ($item['subcategoria'] ?? '-'))
                    . self::renderTableCell((string) ($item['serial'] ?? '-'))
                    . self::renderTableCell((string) ($item['estado'] ?? '-'))
                    . self::renderTableCell((string) ($item['medida'] ?? '-'))
                    . self::renderTableCell((string) ($item['cantidad'] ?? '-'))
                    . self::renderTableCell((string) ($item['ubicacion'] ?? '-'))
                    . self::renderTableCell((string) ($item['responsable'] ?? '-'))
                    . self::renderTableCell(number_format((float) ($item['precio'] ?? 0), 2, ',', '.'))
                    . '</tr>';
            })
            ->implode('');

        return '<div style="overflow-x:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . self::renderHeaderCell('SKU')
            . self::renderHeaderCell('Descripción')
            . self::renderHeaderCell('Marca')
            . self::renderHeaderCell('Categoría')
            . self::renderHeaderCell('Subcat')
            . self::renderHeaderCell('Serial')
            . self::renderHeaderCell('Estado')
            . self::renderHeaderCell('Medida')
            . self::renderHeaderCell('Cant')
            . self::renderHeaderCell('Ubicación')
            . self::renderHeaderCell('Dpto Responsable')
            . self::renderHeaderCell('Precio')
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
