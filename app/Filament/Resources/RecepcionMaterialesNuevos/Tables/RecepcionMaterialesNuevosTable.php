<?php

namespace App\Filament\Resources\RecepcionMaterialesNuevos\Tables;

use App\Models\Category;
use App\Models\Departamento;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\User;
use App\Support\OrdenCompraConformidadService;
use App\Support\OrdenCompraRecepcionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RecepcionMaterialesNuevosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('solicitante_nombre')
                    ->toggleable()
                    ->label('Solicitante')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->solicitadoPor?->name ?: '-'))
                    ->searchable(),

                TextColumn::make('correlativo_odc')
                    ->toggleable()
                    ->label('Correlativo ODC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sumario.correlativo_sdc')
                    ->toggleable()
                    ->label('Sumario')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('solicitud_codigo_control')
                    ->toggleable()
                    ->label('Solicitud')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->codigo_control ?: '-'))
                    ->searchable(),

                TextColumn::make('proveedor.nombre')
                    ->toggleable()
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('para_ser_usado_en')
                    ->toggleable()
                    ->label('Para ser usado en')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->para_ser_usado_en ?: '-'))
                    ->wrap(),

                TextColumn::make('estado')
                    ->toggleable()
                    ->label('Estado')
                    ->badge()
                    ->state(fn ($record): string => self::resolveEstadoLabel((string) ($record->workflow_post_compra ?? '')))
                    ->color(fn ($record): string => self::resolveEstadoColor((string) ($record->workflow_post_compra ?? ''))),

                TextColumn::make('tipo_documento_recepcion')
                    ->toggleable()
                    ->label('Documento recibido')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ((string) $state) {
                        'FACTURA' => 'FACTURA',
                        'NOTA' => 'NOTA DE ENTREGA',
                        default => 'SIN DOCUMENTO',
                    })
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'FACTURA' => 'success',
                        'NOTA' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('total_general')
                    ->toggleable()
                    ->label('Total general')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('factura_path')
                    ->toggleable()
                    ->label('Soporte de entrega')
                    ->state(fn ($record): string => filled($record->factura_path) ? 'Descargar documento' : 'Sin documento')
                    ->url(fn ($record): ?string => filled($record->factura_path)
                        ? route('ordenes-compra.documento-recepcion.download', ['ordenCompra' => $record])
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('verSolicitud')
                    ->label('Ver solicitud')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn ($record): bool => (bool) $record->sumario?->solicitudCompra)
                    ->modalHeading(fn ($record): string => 'Solicitud asociada | ' . (string) ($record->sumario?->solicitudCompra?->codigo_control ?? '-'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn ($record): array => self::getSolicitudViewFormData($record))
                    ->schema(self::getSolicitudViewSchema()),

                Action::make('marcarZonaTransicion')
                    ->label('Marcar en Zona de transicion')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->color('info')
                    ->visible(fn ($record, $livewire): bool => self::isPorRecibirTab($livewire)
                        && (string) ($record->workflow_post_compra ?? '') === 'DOCUMENTO_RECEPCION_CARGADO_PROCURA'
                        && blank($record->recepcion_procesada_at))
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar recepcion en almacen')
                    ->modalDescription('Esta accion notificara al solicitante y habilitara la conformidad de materiales.')
                    ->action(function ($record): void {
                        try {
                            app(OrdenCompraRecepcionService::class)->marcarZonaTransicionAlmacen($record, auth()->user());

                            Notification::make()
                                ->title('Material enviado a zona de transicion')
                                ->body('Se notifico al solicitante para conformidad por item.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo mover a zona de transicion')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('realizarEntrada')
                    ->label('Realizar entrada')
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('success')
                    ->visible(fn ($record, $livewire): bool => self::isPendienteEntradaTab($livewire)
                        && self::hasPendingEntradaItems($record))
                    ->modalWidth('7xl')
                    ->modalHeading('Realizar entrada')
                    ->fillForm(fn ($record): array => self::buildEntradaModalFormData($record))
                    ->schema(self::getEntradaModalSchema())
                    ->action(function (array $data, $record): void {
                        try {
                            app(OrdenCompraConformidadService::class)->procesarEntradaDetallada(
                                $record,
                                auth()->user(),
                                $data,
                                $data['items'] ?? []
                            );

                            Notification::make()
                                ->title('Entrada realizada')
                                ->body('Se procesó la entrada final con el mismo formato de Registro de Materiales.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo realizar la entrada')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('realizarRegistroNuevo')
                    ->label('Realizar registro nuevo')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('warning')
                    ->visible(fn ($record, $livewire): bool => self::isPendienteEntradaTab($livewire)
                        && self::hasPendingRegistroNuevoItems($record))
                    ->modalWidth('7xl')
                    ->modalHeading('Realizar registro nuevo')
                    ->fillForm(fn ($record): array => self::buildIngresoModalFormData($record))
                    ->schema(self::getIngresoModalSchema())
                    ->action(function (array $data, $record): void {
                        try {
                            app(OrdenCompraConformidadService::class)->procesarRegistroNuevoDetallado(
                                $record,
                                auth()->user(),
                                $data,
                                $data['items'] ?? []
                            );

                            Notification::make()
                                ->title('Registro nuevo realizado')
                                ->body('Se registraron los productos nuevos con el mismo formato de Registro de Materiales.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('No se pudo realizar el registro nuevo')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('vistaPreviaOdc')
                    ->label('Vista previa ODC')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('ordenes-compra.formato.print', ['ordenCompra' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function isPorRecibirTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'por_recibir';
    }

    private static function isPendienteEntradaTab(mixed $livewire = null): bool
    {
        return self::resolveActiveTab($livewire) === 'pendiente_entrada';
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

    private static function resolveEstadoLabel(string $workflow): string
    {
        return match ($workflow) {
            'EN_TRANSICION_ALMACEN' => 'DISPONIBLE EN ZONA DE TRANSICION',
            'CONFORMIDAD_POR_ITEMS_COMPLETA' => 'PENDIENTE DE ENTRADA FINAL',
            default => 'RECIBIDO EN ALMACEN',
        };
    }

    private static function resolveEstadoColor(string $workflow): string
    {
        return match ($workflow) {
            'EN_TRANSICION_ALMACEN' => 'info',
            'CONFORMIDAD_POR_ITEMS_COMPLETA' => 'success',
            default => 'warning',
        };
    }

    private static function hasPendingEntradaItems(mixed $record): bool
    {
        return self::buildExistingEntryRows($record) !== [];
    }

    private static function hasPendingRegistroNuevoItems(mixed $record): bool
    {
        return self::buildNewEntryRows($record) !== [];
    }

    private static function buildExistingEntryRows(mixed $record): array
    {
        return self::buildPendingWarehouseRows($record, true);
    }

    private static function buildNewEntryRows(mixed $record): array
    {
        return self::buildPendingWarehouseRows($record, false);
    }

    private static function buildPendingWarehouseRows(mixed $record, bool $requiresExistingProduct): array
    {
        if (! $record) {
            return [];
        }

        return $record->items()
            ->where('decision_solicitante', 'ACEPTADO')
            ->whereNull('procesado_almacen_at')
            ->orderBy('id')
            ->get()
            ->filter(fn ($item): bool => self::matchesExistingInventory((string) ($item->descripcion ?? '')) === $requiresExistingProduct)
            ->map(fn ($item): array => [
                'orden_compra_item_id' => $item->id,
                'item' => (string) ($item->item ?? ('#' . $item->id)),
                'descripcion' => (string) ($item->descripcion ?? ''),
                'cantidad' => (int) ($item->cantidad ?? 1),
                'unidad_medida' => (string) ($item->unidad_medida ?? 'UND'),
                'precio' => (float) ($item->precio_unitario ?? 0),
                'product_id' => null,
            ])
            ->values()
            ->all();
    }

    private static function buildEntradaModalFormData(mixed $record): array
    {
        return array_merge(
            self::buildMovementHeaderFormData($record, 'entrada'),
            [
                'items' => collect(self::buildExistingEntryRows($record))
                    ->map(function (array $row): array {
                        $matchedProduct = self::findMatchingProductByDescription((string) ($row['descripcion'] ?? ''));

                        return [
                            'orden_compra_item_id' => (int) ($row['orden_compra_item_id'] ?? 0),
                            'product_id' => $matchedProduct?->id,
                            'product_id_by_description' => $matchedProduct?->id,
                            'sku_text' => $matchedProduct?->sku,
                            'descripcion' => $matchedProduct?->descripcion ?: (string) ($row['descripcion'] ?? ''),
                            'marca' => $matchedProduct?->marca,
                            'categoria' => $matchedProduct?->subcategory?->category?->name,
                            'subcategoria' => $matchedProduct?->subcategory?->name,
                            'serial' => $matchedProduct?->serial,
                            'estado' => $matchedProduct?->estado,
                            'medida' => $matchedProduct?->medida,
                            'ubicacion' => $matchedProduct?->ubicacion,
                            'responsable' => $matchedProduct?->dpto_responsable,
                            'cantidad' => (int) ($row['cantidad'] ?? 1),
                            'precio' => (float) ($row['precio'] ?? 0),
                        ];
                    })
                    ->values()
                    ->all(),
            ]
        );
    }

    private static function buildIngresoModalFormData(mixed $record): array
    {
        return array_merge(
            self::buildMovementHeaderFormData($record, 'ingreso'),
            [
                'items' => collect(self::buildNewEntryRows($record))
                    ->map(fn (array $row): array => [
                        'line_uuid' => (string) Str::uuid(),
                        'orden_compra_item_id' => (int) ($row['orden_compra_item_id'] ?? 0),
                        'category_id' => null,
                        'subcategory_id' => null,
                        'descripcion' => (string) ($row['descripcion'] ?? ''),
                        'marca' => (string) ($record->proveedor?->nombre ?? ''),
                        'serial' => '',
                        'estado' => 'NUEVO',
                        'medida' => (string) ($row['unidad_medida'] ?? 'UND'),
                        'cantidad' => (int) ($row['cantidad'] ?? 1),
                        'ubicacion' => 'ALMACEN',
                        'dpto_responsable' => (string) ($record->sumario?->solicitudCompra?->departamento_solicitante ?? 'GENERAL'),
                        'rango_min' => 0,
                        'precio' => (float) ($row['precio'] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    private static function buildMovementHeaderFormData(mixed $record, string $type): array
    {
        return [
            'almacenista_visual' => auth()->user()?->name,
            'fecha_visual' => now()->toDateString(),
            'orden_compra' => (string) ($record->correlativo_odc ?? ''),
            'nro_solicitud' => (string) ($record->sumario?->solicitudCompra?->codigo_control ?? ''),
            'factura_nota' => (string) ($record->tipo_documento_recepcion ?? ''),
            'nro_doc_legal' => (string) ($record->correlativo_odc ?? ''),
            'proveedor' => (string) ($record->proveedor?->nombre ?? $record->nombre_proveedor_libre ?? ''),
            'nro_control' => InventoryMovement::generateControlNumber($type),
            'nro_control_mostrado' => InventoryMovement::generateControlNumber($type),
            'comentarios' => 'Entrada oficial por items aceptados para ODC ' . (string) ($record->correlativo_odc ?? $record->id),
        ];
    }

    private static function getEntradaModalSchema(): array
    {
        return [
            Section::make('DATOS GENERALES')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            Select::make('almacenista_visual')
                                ->label('Almacenista')
                                ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'name')->toArray())
                                ->searchable()
                                ->required(),

                            DatePicker::make('fecha_visual')
                                ->label('Fecha')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('orden_compra')
                                ->label('Orden de Compra')
                                ->maxLength(255),

                            TextInput::make('nro_solicitud')
                                ->label('N Solicitud')
                                ->maxLength(255),

                            TextInput::make('factura_nota')
                                ->label('F/N')
                                ->maxLength(255),

                            TextInput::make('nro_doc_legal')
                                ->label('N')
                                ->maxLength(255),

                            TextInput::make('proveedor')
                                ->label('Proveedor')
                                ->maxLength(255)
                                ->columnSpan(4),

                            Hidden::make('nro_control'),

                            TextInput::make('nro_control_mostrado')
                                ->label('N Control')
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
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(function (array $state): ?string {
                            $sku = trim((string) ($state['sku_text'] ?? ''));
                            $descripcion = trim((string) ($state['descripcion'] ?? ''));
                            $cantidad = (string) ($state['cantidad'] ?? '0');

                            if ($descripcion === '') {
                                return 'Producto sin seleccionar';
                            }

                            return 'SKU: ' . $sku . ' | ' . $descripcion . ' | Cant: ' . $cantidad;
                        })
                        ->schema([
                            Hidden::make('orden_compra_item_id')->required(),

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

    private static function getIngresoModalSchema(): array
    {
        return [
            Section::make('DATOS GENERALES')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            Select::make('almacenista_visual')
                                ->label('Almacenista')
                                ->options(fn (): array => User::role('Almacen')->orderBy('name')->pluck('name', 'name')->toArray())
                                ->searchable()
                                ->required(),

                            DatePicker::make('fecha_visual')
                                ->label('Fecha')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('orden_compra')
                                ->label('Orden de Compra')
                                ->maxLength(255),

                            TextInput::make('nro_solicitud')
                                ->label('N Solicitud')
                                ->maxLength(255),

                            TextInput::make('factura_nota')
                                ->label('F/N')
                                ->maxLength(255),

                            TextInput::make('nro_doc_legal')
                                ->label('N')
                                ->maxLength(255),

                            TextInput::make('proveedor')
                                ->label('Proveedor')
                                ->maxLength(255)
                                ->columnSpan(4),

                            Hidden::make('nro_control'),

                            TextInput::make('nro_control_mostrado')
                                ->label('N Control')
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
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(function (array $state): ?string {
                            $descripcion = trim((string) ($state['descripcion'] ?? ''));
                            $cantidad = (string) ($state['cantidad'] ?? '0');

                            if ($descripcion === '') {
                                return 'Producto nuevo sin descripcion';
                            }

                            return $descripcion . ' | Cant: ' . $cantidad;
                        })
                        ->schema([
                            Hidden::make('line_uuid')
                                ->dehydrated(false),

                            Hidden::make('orden_compra_item_id')->required(),

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

                            TextInput::make('dpto_responsable')
                                ->label('Dpto Responsable')
                                ->datalist(fn (): array => Departamento::query()->orderBy('nombre')->pluck('nombre')->all())
                                ->helperText('Puedes escribir libremente o elegir un departamento sugerido.')
                                ->required(),

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

    private static function matchesExistingInventory(string $description): bool
    {
        $normalized = trim(mb_strtolower($description));

        if ($normalized === '') {
            return false;
        }

        return Product::query()
            ->where('is_archived', false)
            ->whereRaw('lower(trim(descripcion)) = ?', [$normalized])
            ->exists();
    }

    private static function findMatchingProductByDescription(string $description): ?Product
    {
        $normalized = trim(mb_strtolower($description));

        if ($normalized === '') {
            return null;
        }

        return Product::query()
            ->with('subcategory.category')
            ->where('is_archived', false)
            ->whereRaw('lower(trim(descripcion)) = ?', [$normalized])
            ->orderBy('id')
            ->first();
    }

    private static function getSolicitudViewSchema(): array
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
                        ->content(fn (callable $get): HtmlString => new HtmlString(self::renderSolicitudItemsTable($get('items') ?? [])))
                        ->dehydrated(false),
                ]),
        ];
    }

    private static function getSolicitudViewFormData(mixed $record): array
    {
        $solicitud = $record->sumario?->solicitudCompra;

        if (! $solicitud) {
            return [];
        }

        $solicitud->loadMissing(['items', 'solicitadoPor', 'porAlmacen', 'aprobadoPor', 'recibidoPor']);

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
        ];
    }

    private static function renderSolicitudItemsTable(array $items): string
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
}

