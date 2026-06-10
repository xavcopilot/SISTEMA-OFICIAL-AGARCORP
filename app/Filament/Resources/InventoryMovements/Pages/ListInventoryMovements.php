<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\Category;
use App\Models\Departamento;
use App\Models\User;
use App\Models\InventoryMovement;
use App\Models\MovementItem;
use App\Models\Product;
use App\Models\Subcategory;
use App\Support\FormDraftStore;
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
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListInventoryMovements extends ListRecords
{
    private const MAX_ENTRADA_ITEMS = 12;
    private const MAX_SALIDA_ITEMS = 13;

    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ingreso')
                ->label('INGRESO')
                ->color('success')
                ->modalHeading('Ingreso de Materiales (Productos Nuevos)')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => $this->loadMovementDraft('ingreso'))
                ->schema($this->ingresoSchema())
                ->extraModalFooterActions(fn (Action $action): array => $this->movementDraftFooterActions($action, 'ingreso'))
                ->action(function (array $data): void {
                    $this->storeIngreso($data);
                }),

            Action::make('entrada')
                ->label('ENTRADA')
                ->color('primary')
                ->modalHeading('Entrada de Materiales (Productos Registrados)')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => $this->loadMovementDraft('entrada'))
                ->schema($this->entradaSchema())
                ->extraModalFooterActions(fn (Action $action): array => $this->movementDraftFooterActions($action, 'entrada'))
                ->action(function (array $data): void {
                    $this->storeEntrada($data);
                }),

            Action::make('salida')
                ->label('SALIDA')
                ->color('danger')
                ->modalHeading('Registro de Salidas de Materiales')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => $this->loadMovementDraft('salida'))
                ->schema($this->salidaSchema())
                ->extraModalFooterActions(fn (Action $action): array => $this->movementDraftFooterActions($action, 'salida'))
                ->action(function (array $data): void {
                    $this->storeSalida($data);
                }),
        ];
    }

    private function movementDraftFooterActions(Action $action, string $movementType): array
    {
        return [
            Action::make('saveTemporary_' . $movementType)
                ->label('Guardar borrador')
                ->color('warning')
                ->cancelParentActions(false)
                ->action(function (Action $action) use ($movementType): void {
                    $this->saveMovementDraftFromAction($action, $movementType);
                }),
            Action::make('clearTemporary_' . $movementType)
                ->label('Limpiar borrador')
                ->color('gray')
                ->cancelParentActions(false)
                ->action(function () use ($movementType): void {
                    $this->clearMovementDraft($movementType);
                    $this->replaceMountedAction($movementType);

                    Notification::make()
                        ->title('Borrador limpiado')
                        ->body('El borrador fue eliminado y el formulario se reinicio.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function loadMovementDraft(string $movementType): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return FormDraftStore::load($user, $this->movementDraftKey($movementType)) ?? [];
    }

    private function saveMovementDraftFromAction(Action $action, string $movementType): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $parentAction = $action->getParentAction();
        $rawData = $parentAction?->getRawData() ?? [];

        FormDraftStore::save($user, $this->movementDraftKey($movementType), is_array($rawData) ? $rawData : []);

        Notification::make()
            ->title('Borrador guardado')
            ->body('Borrador guardado exitosamente.')
            ->success()
            ->send();
    }

    private function clearMovementDraft(string $movementType): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        FormDraftStore::clear($user, $this->movementDraftKey($movementType));
    }

    private function movementDraftKey(string $movementType): string
    {
        return 'inventory_movements:' . $movementType;
    }

    protected function viewFormatPromptAction(): Action
    {
        return Action::make('viewFormatPrompt')
            ->requiresConfirmation()
            ->modalHeading('Movimiento guardado')
            ->modalDescription(function (array $arguments): string {
                $tipo = (string) ($arguments['tipo'] ?? '');

                if (in_array($tipo, ['ingreso', 'entrada'], true)) {
                    return '¿Deseas visualizar ahora el formato de entrada?';
                }

                return '¿Deseas visualizar ahora el formato de salida?';
            })
            ->modalSubmitActionLabel('Ver formato')
            ->modalCancelActionLabel('Luego')
            ->color('primary')
            ->action(function (array $arguments): void {
                $movementId = (int) ($arguments['movementId'] ?? 0);
                $tipo = (string) ($arguments['tipo'] ?? '');

                if ($movementId <= 0) {
                    return;
                }

                $routeName = in_array($tipo, ['ingreso', 'entrada'], true)
                    ? 'inventario.movimientos.formato-entrada'
                    : 'inventario.movimientos.formato-salida';

                $formatUrl = route($routeName, [
                    'inventoryMovement' => $movementId,
                    'download' => 0,
                ]);

                $this->js('window.open(' . json_encode($formatUrl) . ', "_blank")');
            });
    }

    private function ingresoSchema(): array
    {
        return [
            Section::make('DATOS GENERALES')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            Select::make('almacenista_user_id')
                                ->label('Almacenista')
                                ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'id')->toArray())
                                ->default(fn (): ?int => (auth()->user()?->hasRole('Almacen')) ? auth()->id() : null)
                                ->searchable()
                                ->required(),

                            Select::make('entregado_por_user_id')
                                ->label('Entregado por')
                                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable(),

                            DatePicker::make('fecha_visual')
                                ->label('Fecha')
                                ->default(now()->toDateString())
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('orden_compra')
                                ->label('Orden de Compra')
                                ->maxLength(255),

                            TextInput::make('nro_solicitud')
                                ->label('N Solicitud')
                                ->maxLength(255),

                            TextInput::make('factura_nota')
                                ->label('F/N (Tipo Doc.)')
                                ->maxLength(255),

                            TextInput::make('nro_doc_legal')
                                ->label('N° Factura/Nota')
                                ->maxLength(255),

                            TextInput::make('proveedor')
                                ->label('Proveedor')
                                ->maxLength(255)
                                ->columnSpan(4),

                            Hidden::make('nro_control')
                                ->default(fn (): string => InventoryMovement::generateControlNumber('ingreso')),

                            TextInput::make('nro_control_mostrado')
                                ->label('N Control')
                                ->default(fn (): string => InventoryMovement::generateControlNumber('ingreso'))
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),
                        ]),
                ]),

            Section::make('DATOS DEL PRODUCTO')
                ->schema([
                    Repeater::make('items')
                        ->label('Detalle de articulos')
                        ->required()
                        ->minItems(1)
                        ->maxItems(self::MAX_ENTRADA_ITEMS)
                        ->addActionLabel('Agregar')
                        ->helperText('Maximo ' . self::MAX_ENTRADA_ITEMS . ' articulos por entrada.')
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(function (array $state): ?string {
                            $descripcion = trim((string) ($state['descripcion'] ?? ''));
                            $cantidad = (string) ($state['cantidad'] ?? '0');

                            if ($descripcion === '') {
                                return 'Producto nuevo sin descripcion';
                            }

                            return "{$descripcion} | Cant: {$cantidad}";
                        })
                        ->schema([
                            Hidden::make('line_uuid')
                                ->default(fn (): string => (string) Str::uuid())
                                ->dehydrated(false),

                            Select::make('category_id')
                                ->label('Categoria')
                                ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set): mixed => $set('subcategory_id', null)),

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
                                ->required(),

                            Placeholder::make('sku_preview')
                                ->label('SKU (automatico)')
                                ->content(function (callable $get): string {
                                    $categoryId = (int) ($get('category_id') ?? 0);

                                    if ($categoryId <= 0) {
                                        return 'Selecciona una categoria';
                                    }

                                    $items = $get('../../items') ?? [];
                                    $currentUuid = (string) ($get('line_uuid') ?? '');
                                    $offset = 0;

                                    foreach ($items as $row) {
                                        if ((int) ($row['category_id'] ?? 0) !== $categoryId) {
                                            continue;
                                        }

                                        if ((string) ($row['line_uuid'] ?? '') === $currentUuid) {
                                            break;
                                        }

                                        $offset++;
                                    }

                                    return Product::previewSkuForCategoryId($categoryId, $offset) ?? 'Pendiente';
                                }),

                            TextInput::make('descripcion')
                                ->label('Descripcion')
                                ->required(),

                            TextInput::make('marca')
                                ->label('Marca')
                                ->required(),

                            TextInput::make('serial')
                                ->label('Serial'),

                            TextInput::make('estado')
                                ->label('Estado')
                                ->default('NUEVO')
                                ->required(),

                            TextInput::make('medida')
                                ->label('Medida')
                                ->default('UND')
                                ->required(),

                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('ubicacion')
                                ->label('Ubicacion')
                                ->required(),

                            Select::make('dpto_responsable')
                                ->label('Dpto Responsable')
                                ->options(fn (): array => Departamento::query()->orderBy('nombre')->pluck('nombre', 'nombre')->toArray())
                                ->required()
                                ->native(false),

                            TextInput::make('rango_min')
                                ->label('Rango')
                                ->numeric()
                                ->required()
                                ->minValue(0),

                            TextInput::make('precio')
                                ->label('Precio')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->columns(6)
                        ->columnSpanFull(),
                ]),

            Section::make('COMENTARIOS')
                ->schema([
                    Textarea::make('comentarios')
                        ->label('Observaciones')
                        ->rows(3)
                        ->maxLength(2000),
                ]),
        ];
    }

    private function entradaSchema(): array
    {
        return [
            Section::make('DATOS GENERALES')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            Select::make('almacenista_user_id')
                                ->label('Almacenista')
                                ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'id')->toArray())
                                ->default(fn (): ?int => (auth()->user()?->hasRole('Almacen')) ? auth()->id() : null)
                                ->searchable()
                                ->required(),

                            Select::make('entregado_por_user_id')
                                ->label('Entregado por')
                                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable(),

                            DatePicker::make('fecha_visual')
                                ->label('Fecha')
                                ->default(now()->toDateString())
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('orden_compra')
                                ->label('Orden de Compra')
                                ->maxLength(255),

                            TextInput::make('nro_solicitud')
                                ->label('N Solicitud')
                                ->maxLength(255),

                            TextInput::make('factura_nota')
                                ->label('F/N (Tipo Doc.)')
                                ->maxLength(255),

                            TextInput::make('nro_doc_legal')
                                ->label('N° Factura/Nota')
                                ->maxLength(255),

                            TextInput::make('proveedor')
                                ->label('Proveedor')
                                ->maxLength(255)
                                ->columnSpan(4),

                            Hidden::make('nro_control')
                                ->default(fn (): string => InventoryMovement::generateControlNumber('entrada')),

                            TextInput::make('nro_control_mostrado')
                                ->label('N Control')
                                ->default(fn (): string => InventoryMovement::generateControlNumber('entrada'))
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),
                        ]),
                ]),

            Section::make('DATOS DEL PRODUCTO')
                ->schema([
                    Repeater::make('items')
                        ->label('Detalle de articulos')
                        ->required()
                        ->minItems(1)
                        ->maxItems(self::MAX_ENTRADA_ITEMS)
                        ->addable(fn (callable $get): bool => count($get('items') ?? []) < self::MAX_ENTRADA_ITEMS)
                        ->addActionLabel('Agregar')
                        ->helperText('Maximo ' . self::MAX_ENTRADA_ITEMS . ' articulos por entrada.')
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(function (array $state): ?string {
                            $sku = trim((string) ($state['sku_text'] ?? ''));
                            $descripcion = trim((string) ($state['descripcion'] ?? ''));
                            $cantidad = (string) ($state['cantidad'] ?? '0');

                            if ($descripcion === '') {
                                return 'Producto sin seleccionar';
                            }

                            return "SKU: {$sku} | {$descripcion} | Cant: {$cantidad}";
                        })
                        ->schema([
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
                                    $product = Product::query()->with('subcategory.category')->find($state);

                                    $set('sku_text', $product?->sku);
                                    $set('descripcion', $product?->descripcion);
                                    $set('marca', $product?->marca);
                                    $set('categoria', $product?->subcategory?->category?->name);
                                    $set('subcategoria', $product?->subcategory?->name);
                                    $set('serial', $product?->serial);
                                    $set('estado', $product?->estado);
                                    $set('medida', $product?->medida);
                                    $set('ubicacion', $product?->ubicacion);
                                    $set('responsable', $product?->dpto_responsable);
                                })
                                ->helperText('Busca por descripcion y veras SKU + unidades disponibles antes de seleccionar.'),

                            Select::make('product_id')
                                ->label('SKU')
                                ->options(fn (): array => Product::query()->where('is_archived', false)->orderBy('sku')->pluck('sku', 'id')->toArray())
                                ->searchable()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->helperText('En ENTRADA solo se usan SKU existentes. Para crear SKU nuevo usa INGRESO.')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set): void {
                                    $product = Product::query()->with('subcategory.category')->find($state);

                                    $set('product_id_by_description', $state ? (int) $state : null);

                                    $set('sku_text', $product?->sku);
                                    $set('descripcion', $product?->descripcion);
                                    $set('marca', $product?->marca);
                                    $set('categoria', $product?->subcategory?->category?->name);
                                    $set('subcategoria', $product?->subcategory?->name);
                                    $set('serial', $product?->serial);
                                    $set('estado', $product?->estado);
                                    $set('medida', $product?->medida);
                                    $set('ubicacion', $product?->ubicacion);
                                    $set('responsable', $product?->dpto_responsable);
                                }),

                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('precio')
                                ->label('Precio')
                                ->numeric()
                                ->required()
                                ->minValue(0),

                            Hidden::make('sku_text')
                                ->dehydrated(false),

                            TextInput::make('descripcion')
                                ->label('Descripcion')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('marca')
                                ->label('Marca')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('categoria')
                                ->label('Categoria')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('subcategoria')
                                ->label('Sub Categoria')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('serial')
                                ->label('Serial')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('estado')
                                ->label('Estado')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('medida')
                                ->label('Medida')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('ubicacion')
                                ->label('Ubicacion')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('responsable')
                                ->label('Responsable')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(6)
                        ->columnSpanFull(),
                ]),

            Section::make('COMENTARIOS')
                ->schema([
                    Textarea::make('comentarios')
                        ->label('Observaciones')
                        ->rows(3)
                        ->maxLength(2000),
                ]),
        ];
    }

    private function salidaSchema(): array
    {
        return [
            Section::make('DATOS GENERALES')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            Select::make('almacenista_visual')
                                ->label('Almacenista')
                                ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'name')->toArray())
                                ->default(fn (): ?string => (auth()->user()?->hasRole('Almacen')) ? auth()->user()?->name : null)
                                ->searchable()
                                ->required(),

                            DatePicker::make('fecha_visual')
                                ->label('Fecha')
                                ->default(now()->toDateString())
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('dpto_responsable')
                                ->label('Dpto Responsable')
                                ->options(fn (): array => Departamento::query()->orderBy('nombre')->pluck('nombre', 'nombre')->toArray())
                                ->required()
                                ->searchable()
                                ->native(false),

                            Hidden::make('nro_control')
                                ->default(fn (): string => InventoryMovement::generateControlNumber('salida')),

                            TextInput::make('nro_control_mostrado')
                                ->label('N Control')
                                ->default(fn (): string => InventoryMovement::generateControlNumber('salida'))
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),
                        ]),
                ]),

            Section::make('DETALLE DE MATERIALES')
                ->schema([
                    Repeater::make('items')
                        ->label('Detalle')
                        ->required()
                        ->minItems(1)
                        ->maxItems(self::MAX_SALIDA_ITEMS)
                        ->addable(fn (callable $get): bool => count($get('items') ?? []) < self::MAX_SALIDA_ITEMS)
                        ->addActionLabel('Agregar')
                        ->helperText('Maximo ' . self::MAX_SALIDA_ITEMS . ' articulos por salida.')
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(function (array $state): ?string {
                            $sku = trim((string) ($state['product_id'] ?? ''));
                            $descripcion = trim((string) ($state['descripcion'] ?? ''));
                            $cantidad = (string) ($state['cantidad'] ?? '0');
                            $retorna = ((string) ($state['retorna'] ?? '0')) === '1' ? 'SI' : 'NO';

                            if ($descripcion === '') {
                                return 'Producto sin seleccionar';
                            }

                            return "SKU: {$sku} | {$descripcion} | Cant: {$cantidad} | Retorna: {$retorna}";
                        })
                        ->schema([
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
                                    $product = Product::query()->with('subcategory.category')->find($state);

                                    $set('descripcion', $product?->descripcion);
                                    $set('marca', $product?->marca);
                                    $set('categoria', $product?->subcategory?->category?->name);
                                    $set('subcategoria', $product?->subcategory?->name);
                                    $set('serial', $product?->serial);
                                    $set('estado', $product?->estado);
                                    $set('medida', $product?->medida);
                                    $set('ubicacion', $product?->ubicacion);
                                    $set('stock_disponible', $product?->stock_actual);
                                })
                                ->helperText('Busca por descripcion y veras SKU + unidades disponibles antes de seleccionar.'),

                            Select::make('product_id')
                                ->label('SKU')
                                ->options(fn (): array => Product::query()->where('is_archived', false)->orderBy('sku')->pluck('sku', 'id')->toArray())
                                ->searchable()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set): void {
                                    $product = Product::query()->with('subcategory.category')->find($state);

                                    $set('product_id_by_description', $state ? (int) $state : null);

                                    $set('descripcion', $product?->descripcion);
                                    $set('marca', $product?->marca);
                                    $set('categoria', $product?->subcategory?->category?->name);
                                    $set('subcategoria', $product?->subcategory?->name);
                                    $set('serial', $product?->serial);
                                    $set('estado', $product?->estado);
                                    $set('medida', $product?->medida);
                                    $set('ubicacion', $product?->ubicacion);
                                    $set('stock_disponible', $product?->stock_actual);
                                }),

                            TextInput::make('cantidad')
                                ->label('Cantidad a Retirar')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(fn (callable $get): int => (int) (Product::find($get('product_id'))?->stock_actual ?? 0))
                                ->helperText(function (callable $get): ?string {
                                    $product = Product::find($get('product_id'));

                                    if (! $product) {
                                        return null;
                                    }

                                    return 'Disponible: ' . $product->stock_actual;
                                }),

                            TextInput::make('descripcion')
                                ->label('Descripcion')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('marca')
                                ->label('Marca')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('categoria')
                                ->label('Categoria')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('subcategoria')
                                ->label('Sub Categoria')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('serial')
                                ->label('Serial')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('estado')
                                ->label('Estado')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('medida')
                                ->label('Medida')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('ubicacion')
                                ->label('Ubicacion')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('stock_disponible')
                                ->label('Stock Disponible')
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('retorna')
                                ->label('RETORNA')
                                ->options([
                                    '1' => 'SI',
                                    '0' => 'NO',
                                ])
                                ->required()
                                ->default('0'),
                        ])
                        ->columns(6)
                        ->columnSpanFull(),
                ]),

            Section::make('COMENTARIOS')
                ->schema([
                    Textarea::make('comentarios')
                        ->label('OBSERVACIONES')
                        ->rows(4)
                        ->maxLength(2000),
                ]),
        ];
    }

    private function storeIngreso(array $data): void
    {
        $this->clearMovementDraft('ingreso');

        $processedProductIds = [];
        $movementId = null;
        $almacenistaSelection = $this->resolveAlmacenistaSelection($data);
        $entregadoPorUser = $this->resolveUserSelection($data, 'entregado_por_user_id');

        DB::transaction(function () use ($data, $almacenistaSelection, $entregadoPorUser, &$processedProductIds, &$movementId): void {
            $dptoResponsable = $this->resolveMovementDepartment($data);

            $movement = InventoryMovement::create([
                'tipo' => 'ingreso',
                'nro_control' => $data['nro_control'] ?? InventoryMovement::generateControlNumber('ingreso'),
                'almacenista_user_id' => $almacenistaSelection['id'],
                'entregado_por_user_id' => $entregadoPorUser?->id,
                'orden_compra' => $data['orden_compra'] ?? null,
                'nro_solicitud' => $data['nro_solicitud'] ?? null,
                'factura_nota' => $data['factura_nota'] ?? null,
                'nro_doc_legal' => $data['nro_doc_legal'] ?? null,
                'proveedor' => $data['proveedor'] ?? null,
                'entregado_por' => (string) ($entregadoPorUser?->name ?? ''),
                'almacenista' => $almacenistaSelection['name'],
                'dpto_responsable' => $dptoResponsable,
                'comentarios' => $data['comentarios'] ?? null,
            ]);

            $movementId = (int) $movement->id;

            $items = $data['items'] ?? [];

            foreach ($items as $index => $item) {
                $cantidad = (int) ($item['cantidad'] ?? 0);
                $precio = (float) ($item['precio'] ?? 0);

                $product = Product::create([
                    'cod_ingreso' => $movement->nro_control . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'descripcion' => (string) ($item['descripcion'] ?? ''),
                    'marca' => (string) ($item['marca'] ?? ''),
                    'subcategory_id' => (int) ($item['subcategory_id'] ?? 0),
                    'serial' => (string) ($item['serial'] ?? ''),
                    'estado' => (string) ($item['estado'] ?? ''),
                    'medida' => (string) ($item['medida'] ?? ''),
                    'ubicacion' => (string) ($item['ubicacion'] ?? ''),
                    'dpto_responsable' => (string) ($item['dpto_responsable'] ?? ''),
                    'stock_minimo' => (int) ($item['rango_min'] ?? 0),
                    'stock_actual' => $cantidad,
                    'precio_unitario' => $precio,
                    'fecha_adquisicion' => now()->toDateString(),
                    'fecha_ultima_entrada' => now()->toDateString(),
                ]);

                MovementItem::create([
                    'movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => $precio,
                    'retorna' => false,
                ]);

                $processedProductIds[] = (int) $product->id;
            }

            $movement->update([
                'total_items' => count($items),
            ]);
        });

        Notification::make()
            ->title('Ingreso registrado')
            ->body('Los productos nuevos y el movimiento de ingreso fueron guardados.')
            ->success()
            ->send();

        if ($movementId !== null) {
            $this->replaceMountedAction('viewFormatPrompt', [
                'movementId' => (int) $movementId,
                'tipo' => 'ingreso',
            ]);
        }

        $this->notifyCriticalProductsByIds($processedProductIds, 'ingreso');
    }

    private function storeEntrada(array $data): void
    {
        $this->clearMovementDraft('entrada');

        $processedProductIds = [];
        $movementId = null;
        $almacenistaSelection = $this->resolveAlmacenistaSelection($data);
        $entregadoPorUser = $this->resolveUserSelection($data, 'entregado_por_user_id');

        DB::transaction(function () use ($data, $almacenistaSelection, $entregadoPorUser, &$processedProductIds, &$movementId): void {
            $dptoResponsable = $this->resolveMovementDepartment($data);

            $movement = InventoryMovement::create([
                'tipo' => 'entrada',
                'nro_control' => $data['nro_control'] ?? InventoryMovement::generateControlNumber('entrada'),
                'almacenista_user_id' => $almacenistaSelection['id'],
                'entregado_por_user_id' => $entregadoPorUser?->id,
                'orden_compra' => $data['orden_compra'] ?? null,
                'nro_solicitud' => $data['nro_solicitud'] ?? null,
                'factura_nota' => $data['factura_nota'] ?? null,
                'nro_doc_legal' => $data['nro_doc_legal'] ?? null,
                'proveedor' => $data['proveedor'] ?? null,
                'entregado_por' => (string) ($entregadoPorUser?->name ?? ''),
                'almacenista' => $almacenistaSelection['name'],
                'dpto_responsable' => $dptoResponsable,
                'comentarios' => $data['comentarios'] ?? null,
            ]);

            $movementId = (int) $movement->id;

            $items = $data['items'] ?? [];
            $this->assertItemsLimit($items, self::MAX_ENTRADA_ITEMS, 'entrada');
            $this->assertNoDuplicateProductsInItems($items, 'entrada');

            foreach ($items as $item) {
                $product = Product::find((int) ($item['product_id'] ?? 0));

                if (! $product) {
                    continue;
                }

                $cantidad = (int) ($item['cantidad'] ?? 0);
                $precio = (float) ($item['precio'] ?? 0);

                $stockAnterior = (int) ($product->stock_actual ?? 0);
                $precioAnterior = (float) ($product->precio_unitario ?? 0);
                $stockNuevo = $stockAnterior + $cantidad;
                $precioPromedio = $this->calculateWeightedAverageUnitPrice(
                    $stockAnterior,
                    $precioAnterior,
                    $cantidad,
                    $precio
                );

                $product->update([
                    'stock_actual' => $stockNuevo,
                    'precio_unitario' => $precioPromedio,
                    'fecha_ultima_entrada' => now()->toDateString(),
                ]);

                MovementItem::create([
                    'movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => $precio,
                    'retorna' => false,
                ]);

                $processedProductIds[] = (int) $product->id;
            }

            $movement->update([
                'total_items' => count($items),
            ]);
        });

        Notification::make()
            ->title('Entrada registrada')
            ->body('La entrada fue guardada. Puedes abrir el formato desde la tabla.')
            ->success()
            ->send();

        if ($movementId !== null) {
            $this->replaceMountedAction('viewFormatPrompt', [
                'movementId' => (int) $movementId,
                'tipo' => 'entrada',
            ]);
        }

        $this->notifyCriticalProductsByIds($processedProductIds, 'entrada');
    }

    private function resolveAlmacenistaSelection(array $data): array
    {
        $selectedUserId = (int) ($data['almacenista_user_id'] ?? 0);
        $selectedUser = $selectedUserId > 0 ? User::query()->find($selectedUserId) : null;

        return [
            'id' => $selectedUser?->id,
            'name' => (string) ($selectedUser?->name ?? auth()->user()?->name ?? ''),
        ];
    }

    private function resolveUserSelection(array $data, string $field): ?User
    {
        $selectedUserId = (int) ($data[$field] ?? 0);

        return $selectedUserId > 0 ? User::query()->find($selectedUserId) : null;
    }

    private function resolveMovementDepartment(array $data): ?string
    {
        $fromHeader = trim((string) ($data['dpto_responsable'] ?? ''));

        if ($fromHeader !== '') {
            return $fromHeader;
        }

        foreach (($data['items'] ?? []) as $item) {
            $fromItem = trim((string) (($item['dpto_responsable'] ?? $item['responsable'] ?? '')));

            if ($fromItem !== '') {
                return $fromItem;
            }
        }

        return null;
    }

    private function storeSalida(array $data): void
    {
        $this->clearMovementDraft('salida');

        $processedProductIds = [];
        $movementId = null;

        DB::transaction(function () use ($data, &$processedProductIds, &$movementId): void {
            $dptoResponsable = trim((string) ($data['dpto_responsable'] ?? ''));

            $movement = InventoryMovement::create([
                'tipo' => 'salida',
                'nro_control' => $data['nro_control'] ?? InventoryMovement::generateControlNumber('salida'),
                'almacenista' => $data['almacenista_visual'] ?? auth()->user()?->name,
                'dpto_responsable' => $dptoResponsable !== '' ? $dptoResponsable : null,
                'comentarios' => $data['comentarios'] ?? null,
            ]);

            $movementId = (int) $movement->id;

            $items = $data['items'] ?? [];
            $this->assertItemsLimit($items, self::MAX_SALIDA_ITEMS, 'salida');
            $this->assertNoDuplicateProductsInItems($items, 'salida');

            foreach ($items as $item) {
                $product = Product::query()->lockForUpdate()->find((int) ($item['product_id'] ?? 0));

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos seleccionados no existe.',
                    ]);
                }

                $cantidad = (int) ($item['cantidad'] ?? 0);

                if ($cantidad > (int) $product->stock_actual) {
                    throw ValidationException::withMessages([
                        'items' => 'No puedes retirar una cantidad mayor al stock disponible para el SKU ' . $product->sku . '.',
                    ]);
                }

                $product->update([
                    'stock_actual' => (int) $product->stock_actual - $cantidad,
                    'fecha_ultima_salida' => now()->toDateString(),
                ]);

                MovementItem::create([
                    'movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'cantidad' => $cantidad,
                    'precio_momento' => $product->precio_unitario,
                    'retorna' => (bool) ((int) ($item['retorna'] ?? 0)),
                ]);

                $processedProductIds[] = (int) $product->id;
            }

            $movement->update([
                'total_items' => count($items),
            ]);
        });

        Notification::make()
            ->title('Salida registrada')
            ->body('La salida de materiales fue guardada y el stock fue actualizado.')
            ->success()
            ->send();

        if ($movementId !== null) {
            $this->replaceMountedAction('viewFormatPrompt', [
                'movementId' => (int) $movementId,
                'tipo' => 'salida',
            ]);
        }

        $this->notifyCriticalProductsByIds($processedProductIds, 'salida');
    }

    private function calculateWeightedAverageUnitPrice(int $currentStock, float $currentUnitPrice, int $incomingQty, float $incomingUnitPrice): float
    {
        $newStock = $currentStock + $incomingQty;

        if ($newStock <= 0) {
            return round(max(0, $currentUnitPrice), 2);
        }

        $currentValue = $currentStock * $currentUnitPrice;
        $incomingValue = $incomingQty * $incomingUnitPrice;

        return round(max(0, ($currentValue + $incomingValue) / $newStock), 2);
    }

    private function notifyCriticalProductsByIds(array $productIds, string $context): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), fn (int $id): bool => $id > 0)));

        if ($productIds === []) {
            return;
        }

        $criticalSkus = Product::query()
            ->whereIn('id', $productIds)
            ->whereColumn('stock_actual', '<', 'stock_minimo')
            ->orderBy('sku')
            ->pluck('sku')
            ->map(fn ($sku): string => (string) $sku)
            ->all();

        if ($criticalSkus === []) {
            return;
        }

        $preview = array_slice($criticalSkus, 0, 5);
        $body = 'SKUs en critico: ' . implode(', ', $preview);

        if (count($criticalSkus) > 5) {
            $body .= ' y ' . (count($criticalSkus) - 5) . ' mas.';
        }

        Notification::make()
            ->title('Productos en estado critico tras ' . $context)
            ->body($body)
            ->warning()
            ->send();
    }

    private function assertNoDuplicateProductsInItems(array $items, string $tipo): void
    {
        $seen = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            if (isset($seen[$productId])) {
                throw ValidationException::withMessages([
                    'items' => 'No se permite repetir el mismo SKU en una misma ' . $tipo . '. Usa una sola linea por SKU.',
                ]);
            }

            $seen[$productId] = true;
        }
    }

    private function assertItemsLimit(array $items, int $maxItems, string $tipo): void
    {
        if (count($items) > $maxItems) {
            throw ValidationException::withMessages([
                'items' => 'Solo se permiten ' . $maxItems . ' articulos por ' . $tipo . '.',
            ]);
        }
    }
}
